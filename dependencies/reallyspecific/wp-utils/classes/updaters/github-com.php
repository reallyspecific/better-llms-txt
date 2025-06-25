<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Updaters\GitHub;

add_filter('rs_util_updater_theme_update_uri_github.com', __NAMESPACE__ . '\filter_update_uri', 10, 1);
add_filter('rs_util_updater_plugin_update_uri_github.com', __NAMESPACE__ . '\filter_update_uri', 10, 1);
add_filter('rs_util_updater_package_retrieval_uri_github.com', __NAMESPACE__ . '\filter_package_retrieval_uri', 10, 2);
add_filter('rs_util_updater_package_body_github.com', __NAMESPACE__ . '\filter_package_body', 10, 2);
add_filter('rs_util_updater_package_info_github.com', __NAMESPACE__ . '\filter_package_add_download_url', 10, 3);
function filter_update_uri($uri)
{
    $path = parse_url($uri, \PHP_URL_PATH);
    return "https://api.github.com/repos{$path}";
}
function filter_package_retrieval_uri($uri)
{
    return "{$uri}/releases/latest";
}
function filter_package_body($package, $updater)
{
    if (is_string($package)) {
        $package = json_decode($package, \true);
    }
    if (empty($package['tag_name']) || empty($package['zipball_url'])) {
        return $package;
    }
    $meta_file_uri = filter_update_uri($updater->uri) . '/contents/' . $updater->basename;
    $meta_file_uri = add_query_arg('ref', $package['tag_name'], $meta_file_uri);
    $params = [];
    if ($updater->token) {
        $params['headers'] = ['Authorization' => 'Bearer ' . $updater->token];
    }
    $request = wp_remote_get($meta_file_uri, $params);
    if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
        return $package;
    }
    $response = wp_remote_retrieve_body($request);
    $meta_file = json_decode($response, \true);
    $contents = base64_decode($meta_file['content']);
    return $contents;
}
function filter_package_add_download_url($package, $response, $updater)
{
    if (empty($response['tag_name']) || empty($response['zipball_url'])) {
        return $package;
    }
    if ($updater->uri !== $package['UpdateURI']) {
        return $package;
    }
    $params = ['redirection' => 0];
    if ($updater->token) {
        $params['headers'] = ['Authorization' => 'Bearer ' . $updater->token];
    }
    $request = wp_remote_get($response['zipball_url'], $params);
    if (is_wp_error($request)) {
        return $package;
    }
    $headers = wp_remote_retrieve_headers($request);
    if (empty($headers['Location'])) {
        return $package;
    }
    $package['DownloadZipURI'] = $headers['Location'];
    return $package;
}
