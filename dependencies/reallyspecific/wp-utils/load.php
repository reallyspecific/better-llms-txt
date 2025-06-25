<?php

/**
 * Utility library for WordPress plugins and themes.
 * @package ReallySpecific\Utils
 * @since 0.1.0
 */
namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils;

function setup()
{
    autoload_directory(__DIR__ . '/functions');
    spl_autoload_register(function ($class_name) {
        class_loader($class_name);
    });
}
/**
 * Requires all php files in a given directory
 *
 * @param mixed $abs_path
 * @return void
 */
function autoload_directory($abs_path)
{
    $files = glob(rtrim($abs_path, '/') . '/*.php');
    foreach ($files as $file) {
        include_once $file;
    }
}
/**
 * Loads a utility class file if the class does not already exist.
 *
 * @param string $class_name The name of the class to load.
 * @return void
 */
function class_loader(string $class_name, ?string $class_folder = null, ?string $root_namespace = null)
{
    if (class_exists($class_name)) {
        return;
    }
    if (is_null($class_folder)) {
        $class_folder = __DIR__ . '/classes/';
    }
    if (is_null($root_namespace)) {
        $root_namespace = __NAMESPACE__;
    }
    $class_name = str_replace($root_namespace . '\\', '', $class_name);
    $class_path = rtrim($class_folder, '/') . '/' . str_replace('\\', '/', $class_name) . '.php';
    if (file_exists($class_path)) {
        include_once $class_path;
    }
}
function utils_dir()
{
    return __DIR__;
}
function is_debug_mode()
{
    return defined('WP_DEBUG') && \WP_DEBUG;
}
function debug($return_value, $log_message, $status = 'warning')
{
    return $return_value;
}
if (defined('ABSPATH')) {
    setup();
}
