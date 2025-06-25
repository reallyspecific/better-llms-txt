<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils;

trait Service_Host
{
    protected $services = [];
    public function attach_service($load_action, $service_name, $callback, $callback_args = [], $admin_only = \false, $load_priority = 10)
    {
        if ($admin_only && !is_admin()) {
            return;
        }
        add_action($load_action, function () use ($service_name, $callback, $callback_args) {
            $this->load_service($service_name, $callback, $callback_args);
        }, $load_priority);
    }
    public function load_service($name, $callback, $callback_args = [])
    {
        if (is_string($callback) && class_exists($callback)) {
            $this->services[$name] = new $callback($this, ...$callback_args);
        } else if (is_callable($callback)) {
            $this->services[$name] = call_user_func_array($callback, [$this] + $callback_args);
        }
    }
    public function &service($name)
    {
        return $this->services[$name];
    }
}
