<?php
    /**
     * Registry class
     * @author      Gerald Plusquellec
     */

    namespace Thin;
    class Registry
    {
        public static $instance;
        public static $objects = array();

        // singleton
        public static function forge()
        {
            if (!static::$instance instanceof self) {
                static::$instance = new self();
            }
            return static::$instance;
        }

        public static function get($key)
        {
            return Arrays::exists($key, static::$objects) ? static::$objects[$key] : null;
        }

        public static function set($key, $value = null)
        {
            if (is_array($key) || is_object($key)) {
                foreach ($key as $k => $v) {
                    static::$objects[$k] = $v;
                }
            } else {
                static::$objects[$key] = $value;
            }
        }

        public static function delete($key)
        {
            static::$objects[$key] = null;
        }

        public static function has($key)
        {
            return Arrays::exists($key, static::$objects);
        }
    }
