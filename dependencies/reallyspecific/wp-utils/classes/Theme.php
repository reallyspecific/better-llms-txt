<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils;

abstract class Theme extends Plugin
{
    protected $assets = ['stylesheets' => [], 'scripts' => []];
    function __construct(array $props = [])
    {
        parent::__construct(['update_plugin_filter' => 'update_themes', ...$props]);
        $this->attach_assets($props['stylesheets'] ?? [], 'stylesheet');
        $this->attach_assets($props['scripts'] ?? [], 'script');
        add_action('wp_enqueue_scripts', [$this, 'install_public_assets']);
        add_action('admin_enqueue_scripts', [$this, 'install_admin_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'install_editor_assets']);
    }
    public function get_root_file()
    {
        if (is_null($this->root_file)) {
            return get_stylesheet_directory() . '/style.css';
        }
        return $this->root_file;
    }
    protected function load_wp_data()
    {
        $theme = wp_get_theme(basename($this->root_path));
        $this->data = $theme;
        return $theme;
    }
    public function get_wp_data($key = null)
    {
        if (empty($key)) {
            return $this->data;
        }
        return $this->data->get($key) ?? null;
    }
    private function attach_assets($assets, $type, $dest = 'public')
    {
        foreach ($assets as $handle => $resource) {
            list($name, $dest) = explode('|', $handle) + [null, $dest];
            if (!is_array($resource)) {
                $resource = ['path' => $resource];
            }
            $asset_path = substr($resource['path'], 0, 1) === '/' ? $resource['path'] : get_theme_file_path($resource['path']);
            $asset_uri = get_theme_file_uri($resource['path']);
            $dep_path = dirname($asset_path) . '/' . basename($asset_path, '.js') . '.asset.php';
            if (file_exists($dep_path)) {
                $dep = include $dep_path;
                foreach ($dep as $key => $value) {
                    $resource[$key] ??= $value;
                }
            }
            $this->assets[$type . 's'][] = ['name' => $name, 'dest' => $dest, 'url' => $asset_uri, 'path' => $asset_path, 'version' => $resource['version'] ?? $this->get_version(), 'dependencies' => $resource['dependencies'] ?? []];
        }
    }
    public function install_textdomain()
    {
        load_theme_textdomain($this->i18n_domain, \false, $this->i18n_path);
    }
    public function __get($name)
    {
        switch ($name) {
            case 'version':
                return $this->get_version();
            default:
                return parent::__get($name);
        }
    }
    public function get_version()
    {
        $version = wp_cache_get('version', $this->name);
        if (!$version) {
            $version = include get_theme_file_path('assets/dist/version.php');
            if (empty($version)) {
                $version = wp_get_theme()->get('Version');
            }
            wp_cache_set('version', $version, $this->name);
        }
        return $version;
    }
    public function install_public_assets()
    {
        $this->install_scripts('public');
        $this->install_styles('public');
    }
    public function install_admin_assets()
    {
        $this->install_scripts('admin');
        $this->install_styles('admin');
    }
    public function install_editor_assets()
    {
        $this->install_scripts('editor', \false);
        foreach ($this->assets['stylesheets'] as $stylesheet) {
            if ($stylesheet['dest'] !== 'editor') {
                continue;
            }
            add_editor_style($stylesheet['url']);
        }
    }
    private function install_scripts($dest, $in_footer = \true)
    {
        foreach ($this->assets['scripts'] as $script) {
            if ($script['dest'] !== $dest) {
                continue;
            }
            wp_enqueue_script($script['name'], $script['url'], $script['dependencies'] ?? [], $script['version'] ?? $this->get_version(), $script['in_footer'] ?? $in_footer);
        }
    }
    private function install_styles($dest)
    {
        foreach ($this->assets['stylesheets'] as $stylesheet) {
            if ($stylesheet['dest'] !== $dest) {
                continue;
            }
            wp_enqueue_style($stylesheet['name'], $stylesheet['url'], $stylesheet['dependencies'] ?? [], $stylesheet['version'] ?? $this->get_version());
        }
    }
}
