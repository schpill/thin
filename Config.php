<?php
    namespace Thin;
    class Config
    {
        /**
         * All of the loaded configuration items.
         *
         * The configuration arrays are keyed by their owning bundle and file.
         *
         * @var array
         */
        public static $items = array();

        /**
         * A cache of the parsed configuration items.
         *
         * @var array
         */
        public static $cache = array();

        public static function init()
        {
            static::$items = Bootstrap::$bag['config']->assoc();
        }

        public static function reset()
        {
            static::$items = Bootstrap::$bag['config']->assoc();
        }

        public static function load($file)
        {
            if (File::exists($file)) {
                $config = include $file;
                static::$items = array_merge(static::$items, $config);
            } else {
                $file = APPLICATION_PATH . DS . 'config' . DS . $file . '.php';
                if (File::exists($file)) {
                    $config = include $file;
                    static::$items = array_merge(static::$items, $config);
                }
            }
        }

        public static function get($key, $default = null)
        {
            return arrayGet(static::$items, $key, $default);
        }

        public static function set($key, $value = null)
        {
            return arraySet(static::$items, $item, $value);
        }

        public static function has($key)
        {
            return !is_null(static::get($key));
        }
    }
