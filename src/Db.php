<?php
    namespace Thin;

    class Db
    {
        public static function connexion()
        {
            $args = func_get_args();

            $class = '\\Thin\\Db\\' . ucfirst(
                Inflector::lower(
                    Arrays::first($args)
                )
            );

            array_shift($args);

            return call_user_func_array([$class, 'instance'], $args);
        }

        public static function __callStatic($method, $args)
        {
            $toArgs = [];

            array_push($toArgs, $method);

            if (count($args)) {
                foreach ($args as $arg) {
                    array_push($toArgs, $arg);
                }
            }

            return call_user_func_array(['\\Thin\\Db', 'connexion'], $toArgs);
        }
    }
