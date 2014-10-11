<?php
    namespace Thin;

    class Autoloader
    {
        private static  $_paths     = array();
        private static  $_classes   = array();
        public static   $calls      = 0;

        public static function registerNamespace($ns, $path)
        {
            if (!array_key_exists($ns, static::$_paths)) {
                static::$_paths[$ns] = $path;
            }
        }

        public static function registerNamespaces(array $namespaces)
        {
            if (count($namespaces)) {
                foreach ($namespaces as $ns => $path) {
                    static::registerNamespace($ns, $path);
                }
            }
        }

        public static function autoload($className)
        {
            $found = false;

            foreach (static::$_paths as $ns => $path) {
                $file = $path . preg_replace('#\\\|_(?!.+\\\)#', DS, str_replace($ns, '', $className)) . '.php';

                if (is_readable($file)) {
                    static::$calls++;

                    require_once $file;

                    static::$_classes[$className] = true;

                    $found = true;
                }
            }
        }
    }

