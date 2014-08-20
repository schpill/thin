<?php
    namespace Thin;

    class Instancedata
    {
        public static function set($key, $value)
        {
            return Config::set('instances.' . $key, $value);
        }

        public static function get($key, $default = null)
        {
            return Config::get('instances.' . $key, $default);
        }

        public static function has($key)
        {
            return Config::has('instances.' . $key);
        }

        public static function forget($key)
        {
            return Config::forget('instances.' . $key);
        }
    }
