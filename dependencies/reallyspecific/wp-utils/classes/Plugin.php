<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils;

use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Settings;
use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Service_Host;
use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Updatable;
abstract class Plugin
{
    use Service_Host;
    protected $root_path = null;
    protected $root_file = null;
    protected $services = [];
    protected $i18n_domain = null;
    protected $i18n_path = null;
    protected $name = null;
    protected $slug = null;
    protected $settings = [];
    protected $data = [];
    protected $updater = null;
    /**
     * Creates a new instance of the plugin.
     * @return Plugin
     */
    public static function new(array $props = []): Plugin
    {
        return new static($props);
    }
    /**
     * Not necessary to be implemented, executed at the end of the constructor method.
     *
     * @return void
     */
    public function setup(): void
    {
    }
    /**
     * Plugin constructor.
     *
     * @param array $props
     * @throws \Exception
     */
    function __construct(array $props = [])
    {
        if (empty($props['name'])) {
            throw new \Exception('Plugin was constructed without a `name` property.');
        }
        if (empty($props['file'])) {
            throw new \Exception('Plugin was constructed without a `file` property.');
        }
        $this->root_file = $props['file'];
        $this->root_path = trailingslashit(dirname($this->root_file));
        $this->i18n_domain = $props['i18n_domain'] ?? null;
        $this->i18n_path = $props['i18n_path'] ?? $this->get_root_path() . 'languages';
        $this->name = $props['name'];
        $this->slug = $props['slug'] ?? sanitize_title(basename($this->root_path));
        add_action('init', [$this, 'get_wp_data']);
        add_action('init', [$this, 'setup_updater']);
        add_action('init', [$this, 'install_textdomain']);
        add_action('plugins_loaded', [$this, 'setup']);
        $this->register_settings();
    }
    /**
     * Not necessary to be implemented, executed at the end of the constructor method.
     *
     * @return void
     */
    public function register_settings($namespaces = []): void
    {
        foreach ($namespaces as $namespace => $props) {
            $this->settings[$namespace] = new Settings($props);
        }
        add_action('wp_loaded', [$this, 'install_settings'], 10, 0);
    }
    /**
     * Not necessary to be implemented, executed at the end of the constructor method.
     *
     * @return void
     */
    public function install_settings(array $settings = []): void
    {
        foreach ($this->settings as $namespace => $props) {
            $this->settings[$namespace]->setup($settings[$namespace] ?? []);
        }
    }
    public function setup_updater()
    {
        if (empty($this->get_wp_data('UpdateURI'))) {
            return;
        }
        $this->updater = new Updater(['object' => $this, 'update_uri' => $this->get_wp_data('UpdateURI'), 'slug' => $this->slug, 'file' => $this->root_file]);
    }
    protected function load_wp_data()
    {
        if (!function_exists('\get_plugin_data')) {
            include_once \ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin = \get_plugin_data($this->root_file);
        $this->data = $plugin;
        return $plugin;
    }
    public function get_wp_data($key = null)
    {
        if (empty($this->data) && did_action('init')) {
            $this->load_wp_data();
        }
        if (empty($key)) {
            return $this->data;
        }
        return $this->data[$key] ?? null;
    }
    public function install_textdomain()
    {
        if (!empty($this->domain)) {
            load_plugin_textdomain($this->domain, \false, $this->i18n_path);
        }
    }
    public function __get($key)
    {
        switch ($key) {
            case 'name':
                return $this->get_name();
            case 'domain':
            case 'text_domain':
            case 'i18n_domain':
                return $this->get_domain();
            case 'slug':
                return $this->slug;
            case 'file':
                return $this->get_root_file();
            case 'path':
                return $this->get_root_path();
            case 'update_uri':
                return $this->get_wp_data('UpdateURI');
            case 'data':
                return $this->data;
            default:
                return null;
        }
    }
    public function get_domain()
    {
        if (empty($this->i18n_domain) && did_action('init')) {
            $this->i18n_domain = $this->get_wp_data('TextDomain');
        }
        return $this->i18n_domain ?? null;
    }
    public function get_name()
    {
        return $this->name;
    }
    public function get_root_path()
    {
        return $this->root_path;
    }
    public function get_root_file()
    {
        return $this->root_file;
    }
    public function get_url($relative_path = null)
    {
        return untrailingslashit(plugins_url($relative_path, $this->get_root_file()));
    }
    public function get_path($relative_path = '')
    {
        return untrailingslashit($this->get_root_path() . $relative_path);
    }
    public function debug_mode()
    {
        return is_debug_mode();
    }
    public function update_check($update, $item, $plugin_file)
    {
        if ($plugin_file !== $this->root_file) {
            return $update;
        }
        // TODO: implement update check
        return $update;
    }
    public function &settings($namespace = 'default')
    {
        return $this->settings[$namespace];
    }
    public function get_setting($namespace = 'default', $key = null)
    {
        $settings = $this->settings[$namespace] ?? null;
        if (empty($settings)) {
            return null;
        }
        return $settings->get($key);
    }
    public function get_template_part(string $slug, ?string $name = null, array $args = [])
    {
        $args = wp_parse_args($args, ['extension_type' => '.php', 'theme_folder' => null]);
        if (!empty($args['theme_folder'])) {
            $path = $args['theme_folder'] . '/' . $slug;
        }
        ob_start();
        $found = get_template_part($path ?? $slug, $name, $args);
        $output = ob_get_clean();
        if ($found) {
            return $output;
        }
        $file_path = $this->get_root_path() . 'templates/' . $slug;
        if ($name && file_exists($file_path . '-' . $name . '.php')) {
            $template = $file_path . '-' . $name . '.php';
        } elseif (file_exists($file_path . '.php')) {
            $template = $file_path . '.php';
        } else {
            return \false;
        }
        $encapsulator = function ($template_file_path) use ($slug, $name, $args) {
            ob_start();
            if (pathinfo($template_file_path, \PATHINFO_EXTENSION) === '.php') {
                include $template_file_path;
            } else {
                return file_get_contents($template_file_path);
            }
            return ob_get_clean();
        };
        return $encapsulator($template);
    }
}
