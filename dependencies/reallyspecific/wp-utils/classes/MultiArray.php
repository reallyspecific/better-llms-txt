<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils;

use function sanitize_title;
use ArrayIterator;
use Exception;
class MultiArray extends ArrayIterator
{
    public function __construct(array|object $array = [])
    {
        $new_array = $this->rebuild_dot_array((array) $array);
        parent::__construct($new_array);
    }
    public function to_array(): array
    {
        return $this->getArrayCopy();
    }
    public function to_json($flags = null): string
    {
        return json_encode($this->getArrayCopy(), $flags);
    }
    private function rebuild_dot_array(array $array)
    {
        if (array_is_list($array)) {
            return $array;
        }
        $new_array = [];
        foreach ($array as $key => $value) {
            $key_parts = explode('.', $key);
            $level =& $new_array;
            while (count($key_parts) > 0) {
                $part = sanitize_title(array_shift($key_parts));
                if (count($key_parts) === 0) {
                    continue;
                }
                if (isset($level[$part]) && !is_array($level[$part])) {
                    throw new Exception("MultiArray: Index {$part} of {$key} is not an array");
                }
                if (!isset($level[$part]) && count($key_parts) > 0) {
                    $level[$part] = [];
                }
                $level =& $level[$part];
            }
            if (is_array($value)) {
                $value = $this->rebuild_dot_array($value);
                if (isset($level[$part]) && is_array($level[$part])) {
                    $value = [$level[$part], ...$value];
                }
            }
            $level[$part] = $value;
        }
        return $new_array;
    }
    public function offsetGet(mixed $key): mixed
    {
        $array = $this->getArrayCopy();
        $key = explode('.', $key);
        while (count($key) > 1) {
            $index = sanitize_title(array_shift($key));
            if (!isset($array[$index])) {
                return null;
            }
            $array =& $array[$index];
        }
        $key = sanitize_title(current($key));
        if (!isset($array[$key])) {
            return null;
        }
        return is_array($array[$key]) && !array_is_list($array[$key]) ? new MultiArray($array[$key]) : $array[$key];
    }
    public function offsetSet(mixed $key, mixed $value): void
    {
        $copy = $this->getArrayCopy();
        $array =& $copy;
        $parts = explode('.', $key);
        $key = sanitize_title($parts[0]);
        if (count($parts) === 1 && is_null($value)) {
            parent::offsetUnset($key);
            return;
        }
        while (count($parts) > 0) {
            $index = sanitize_title(array_shift($parts));
            if (count($parts) > 0) {
                if (!isset($array[$index])) {
                    $array[$index] = [];
                }
                $array =& $array[$index];
            }
        }
        if (is_null($value)) {
            unset($array[$index]);
        } else {
            $array[$index] = $value;
        }
        parent::offsetSet($key, $copy[$key]);
    }
    public function offsetUnset(mixed $key): void
    {
        $this->offsetSet($key, null);
    }
}
