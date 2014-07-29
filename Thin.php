<?php
    namespace Thin;

    class Thin extends Context
    {
        public function getClass()
        {
            return Inflector::uncamelize(repl('Thin\\', '', get_called_class()));
        }

        public static function set($key, $value = null)
        {
            context('thin')->set($key, $value);
        }

        public static function get($key, $default = null)
        {
            return context('thin')->get($key, $default);
        }

        public static function has($key, $default = null)
        {
            return context('thin')->has($key);
        }
    }
