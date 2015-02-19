<?php
    namespace Thin;

    use Closure;

    class Trick
    {
        private static $methods = [];

        public static function set($method, Closure $trick)
        {
            $tricks = isAke(static::$methods, $method, []);

            static::$methods[$method] = $tricks;

            array_push(static::$methods[$method], $trick);
        }

        public static function run($method, $args = [])
        {
            $tricks = isAke(static::$methods, $method, []);

            if (count($tricks)) {
                foreach ($tricks as $trick) {
                    $res = call_user_func_array($trick, $args);
                }
            }

            if (is_callable($method)) {
                return call_user_func_array($method, $args);
            }
        }
    }
