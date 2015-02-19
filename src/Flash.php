<?php
    namespace Thin;

    class Flash
    {
        public static function set($key, $value)
        {
            return Sessionstore::instance('session', 'flash')->set($key, $value);
        }

        public static function get($key, $default = null)
        {
            return Sessionstore::instance('session', 'flash')->get($key, $default);
        }

        public static function getPrev($key, $default = null)
        {
            return Sessionstore::instance('session', 'flashprev')->get($key, $default);
        }

        public static function has($key)
        {
            return Sessionstore::instance('session', 'flash')->has($key);
        }

        public static function del($key)
        {
            return Sessionstore::instance('session', 'flash')->del($key);
        }

        public static function delete($key)
        {
            return Sessionstore::instance('session', 'flash')->del($key);
        }

        public static function forget($key)
        {
            return Sessionstore::instance('session', 'flash')->del($key);
        }

        public static function flush()
        {
            return Sessionstore::instance('session', 'flash')->duplicate(
                Sessionstore::instance('session', 'flashprev')
            )->flush();
        }

        public static function __callStatic($method, $args)
        {
            if (0 == count($args)) {
                return static::get($method);
            } elseif (1 == count($args)) {
                return static::set($method, Arrays::first($args));
            }
        }

        public function __call($method, $args)
        {
            if (0 == count($args)) {
                return static::get($method);
            } elseif (1 == count($args)) {
                return static::set($method, Arrays::first($args));
            }
        }
    }
