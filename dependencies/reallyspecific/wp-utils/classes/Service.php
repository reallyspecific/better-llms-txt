<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils;

abstract class Service
{
    protected $enabled = \true;
    protected $plugin = null;
    protected const SETTINGS_NAMESPACE = null;
    /**
     * Constructor for the class.
     *
     * @param Plugin $plugin The plugin object.
     */
    public function __construct(Plugin &$plugin)
    {
        $this->plugin =& $plugin;
    }
    public static function maybe_register_settings()
    {
        if (static::SETTINGS_NAMESPACE) {
            add_action('admin_init', [static::class, 'register_settings']);
        }
    }
    public static function register_settings()
    {
    }
    /**
     * Get the plugin object.
     *
     * @return Plugin The plugin object.
     */
    public function get_plugin()
    {
        return $this->plugin;
    }
    public function is_enabled()
    {
        return $this->enabled;
    }
    public function enable()
    {
        $this->enabled = \true;
    }
    public function disable()
    {
        $this->enabled = \false;
    }
    public function __get($name)
    {
        switch ($name) {
            case 'plugin':
                return $this->get_plugin();
            case 'enabled':
                return $this->is_enabled();
            default:
                return null;
        }
    }
    public function &settings()
    {
        if (static::SETTINGS_NAMESPACE) {
            return $this->plugin->settings(static::SETTINGS_NAMESPACE);
        }
        return $this->plugin->settings();
    }
    public function get_setting($key)
    {
        return $this->settings()->get($key);
    }
}
