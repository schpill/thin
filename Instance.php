<?php
    namespace Thin;

    class Instance
    {
        private static $instances = array();

        public static function set($class, $key, $instance)
        {
            static::$instances[$class][$key] = $instance;
            return $instance;
        }

        public static function make($class, $key, $instance)
        {
            return static::set($class, $key, $instance);
        }

        public static function has($class, $key)
        {
            $classInstances = isAke(static::$instances, $class);
            $keyInstance    = isAke($classInstances, $key, null);
            return !is_null($keyInstance);
        }

        public static function get($class, $key)
        {
            $classInstances = isAke(static::$instances, $class);
            $keyInstance    = isAke($classInstances, $key, null);
            return $keyInstance;
        }
    }
