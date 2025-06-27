<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Text;

use ReallySpecific\BetterLLMStxt\Dependencies\Parsedown;
function array_to_attr_string($attributes = [])
{
    $attr_string = '';
    foreach ($attributes as $key => $value) {
        if (is_null($value)) {
            continue;
        }
        if ($value === \true) {
            $value = 'true';
        }
        if ($value === \false) {
            $value = 'false';
        }
        if (!is_string($value)) {
            $value = json_encode($value, \true) ?: '';
        }
        $attr_string .= ' ' . $key . '="' . esc_attr($value) . '"';
    }
    return trim($attr_string);
}
function parsedown_text(string $text, string $field = '', string $context = '')
{
    static $parsedown;
    if (!isset($parsedown)) {
        $parsedown = new Parsedown();
        do_action_ref_array('rs_util_text_parsedown_text_instance', [&$parsedown]);
    }
    $text = $parsedown->text($text);
    $text = apply_filters('rs_util_text_parsedown_text', $text, $field, $context, $parsedown);
    return $text;
}
function parsedown_line(string $text, string $field = '', string $context = '')
{
    static $parsedown;
    if (!isset($parsedown)) {
        $parsedown = new Parsedown();
        do_action_ref_array('rs_util_text_parsedown_line_instance', [&$parsedown]);
    }
    $text = $parsedown->line($text);
    $text = apply_filters('rs_util_text_parsedown_line', $text, $field, $context, $parsedown);
    return $text;
}
