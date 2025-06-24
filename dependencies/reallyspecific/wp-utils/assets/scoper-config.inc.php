<?php

declare (strict_types=1);
namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Scoper;

if (!isset($project_source_dir)) {
    echo "Please set the \$project_source_dir variable to an absolute path before including this file.";
    exit;
}
if (!isset($util_source_dir)) {
    $util_source_dir = $project_source_dir . '/vendor/reallyspecific/wp-utils';
}
function get_wp_excluded_symbols(string $file_name, string $project_dir): array
{
    $filePath = $project_dir . '/vendor/sniccowp/php-scoper-wordpress-excludes/generated/' . $file_name;
    return json_decode(file_get_contents($filePath), \true);
}
$wp_constants = get_wp_excluded_symbols('exclude-wordpress-constants.json', $project_source_dir);
$wp_classes = get_wp_excluded_symbols('exclude-wordpress-classes.json', $project_source_dir);
$wp_functions = get_wp_excluded_symbols('exclude-wordpress-functions.json', $project_source_dir);
if (empty($finder)) {
    $finder = \ReallySpecific\BetterLLMStxt\Dependencies\Isolated\Symfony\Component\Finder\Finder::class;
}
return ['output-dir' => __DIR__ . '/dependencies', 'finders' => [$finder::create()->files()->ignoreVCS(\true)->in($project_source_dir . '/vendor/erusev')->name('*.php'), $finder::create()->files()->ignoreVCS(\true)->exclude(['node_modules', 'vendor', 'tests', '.cache'])->in($util_source_dir)->name(['*.php', '*.css', '*.js', '*.csv', '*.svg', '*.map'])], 'php-version' => '8.2', 'patchers' => [static function (string $filePath, string $prefix, string $contents): string {
    if (str_contains($prefix, 'ReallySpecific')) {
        return str_replace("{$prefix}\\ReallySpecific\\Utils", "{$prefix}\\RS_Utils", $contents);
    }
    return $contents;
}], 'exclude-namespaces' => ['~^((?!Parsedown).)$~'], 'exclude-classes' => $wp_classes, 'exclude-constants' => $wp_constants, 'exclude-functions' => $wp_functions, 'expose-global-constants' => \true, 'expose-global-classes' => \true, 'expose-global-functions' => \true, 'expose-namespaces' => ['~^((?!Parsedown).)$~'], 'expose-classes' => [], 'expose-functions' => [], 'expose-constants' => []];
