<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Assets;

use function ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\utils_dir;
use Exception;
function path(string $path = '', $debug = null)
{
    if (is_null($debug)) {
        $debug = apply_filters('rs_util_assets_debug', $debug);
    }
    $source = rtrim(utils_dir() . '/assets', '/') . '/' . ltrim($path, '/');
    if (!$debug) {
        $extension = pathinfo($source, \PATHINFO_EXTENSION);
        if ($extension) {
            $compiled = dirname($source) . '/' . basename($source, '.' . $extension) . '.min.' . $extension;
            if (file_exists($compiled)) {
                return $compiled;
            }
        }
    }
    return $source;
}
function url(string $path = '', $debug = null)
{
    $path = path($path, $debug);
    return plugins_url(basename($path), $path);
}
function version()
{
    try {
        return file_get_contents(path('version.php'));
    } catch (Exception $e) {
        return null;
    }
}
