<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Filesystem;

use function ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\debug;
use function ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Assets\path as utils_assets_dir;
use ZipArchive;
/**
 * Creates a directory, recursively building subfolders as needed
 *
 * @param [type] $path
 * @return bool mkdir was successful
 */
function recursive_mk_dir($path): bool
{
    $parent_path = dirname($path);
    if (!is_dir($parent_path)) {
        $success = recursive_mk_dir($parent_path);
        if (!$success) {
            return \false;
        }
    }
    if (!is_dir($path)) {
        return mkdir($path, 0755);
    }
    return \true;
}
function recursive_rm_dir(string $src): void
{
    $dir = opendir($src);
    while (\false !== $file = readdir($dir)) {
        if ($file != '.' && $file != '..') {
            $full = $src . '/' . $file;
            if (is_dir($full)) {
                recursive_rm_dir($full);
            } else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
}
/**
 * Takes multiple URLs or file paths and combines them into a single string.
 *
 * @param string ...$paths paths to join, only the first can be absolute.
 * @return string The combined paths
 */
function join_path(...$paths): string
{
    if (empty($paths)) {
        return '';
    }
    $combined = rtrim(array_shift($paths), '/');
    while (count($paths) > 0) {
        $combined .= '/' . ltrim(array_shift($paths), '/');
    }
    return canonical_path($combined);
}
/**
 * Reduces a path to its canonical form by removing
 * relative segments and normalizing the path.
 *
 * @param string $path
 * @param string $separator
 * @return string
 */
function canonical_path(string $path, string $separator = '/'): string
{
    $canonical = [];
    $path = explode($separator, $path);
    foreach ($path as $segment) {
        switch ($segment) {
            case '.':
                break;
            case '..':
                if (count($canonical)) {
                    array_pop($canonical);
                }
                break;
            default:
                $canonical[] = $segment;
                break;
        }
    }
    return rtrim(join($separator, $canonical), $separator);
}
function get_extension_from_mime_type(string $mime_type): ?string
{
    // wordpress's mime list is too limited, we gonna use our own
    $mime_list = wp_cache_get('mime_types', 'rs_wp_util');
    if (!$mime_list) {
        $csv_list = fopen(utils_assets_dir() . '/mime-types.csv', 'r');
        $mime_list = [];
        while (($line = fgetcsv($csv_list)) !== \false) {
            if (isset($mime_list[$line[1]])) {
                $mime_list[$line[1]] .= '|' . $line[0];
            } else {
                $mime_list[$line[1]] = $line[0];
            }
        }
        wp_cache_set('mime_types', $mime_list, 'rs_wp_util');
    }
    return $mime_list[$mime_type] ?? null;
}
function make_zip_from_folder(string $source_folder, string $destination_zip_file): bool
{
    $zip_archive = new ZipArchive();
    if (file_exists($destination_zip_file)) {
        unlink($destination_zip_file);
    }
    if ($zip_archive->open($destination_zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== \true) {
        return debug(\false, 'Failed to create zip archive');
    }
    recursive_add_dir_to_zip($zip_archive, $source_folder);
    if ($zip_archive->status != ZIPARCHIVE::ER_OK) {
        return debug(\false, 'Failed to write files to zip');
    }
    $zip_archive->close();
    return \true;
}
function recursive_add_dir_to_zip(&$zip_archive, string $source_folder, string $rel = '/'): void
{
    $dir = opendir($source_folder);
    if (!$dir) {
        return;
    }
    while (\false !== $file = readdir($dir)) {
        if ($file !== '.' && $file !== '..') {
            $full = $source_folder . '/' . $file;
            if (is_dir($full)) {
                recursive_add_dir_to_zip($zip_archive, $full, $rel . $file . '/');
            } else {
                $zip_archive->addFile($full, $rel . $file);
            }
        }
    }
    closedir($dir);
}
