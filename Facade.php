<?php
    namespace Thin;
    use RuntimeException;

    abstract class Facade
    {
        public static function __callStatic($method, $args)
        {
            $instance = static::instance();

            switch (count($args)) {
                case 0:
                    return $instance->$method();
                case 1:
                    return $instance->$method($args[0]);
                case 2:
                    return $instance->$method($args[0], $args[1]);
                case 3:
                    return $instance->$method($args[0], $args[1], $args[2]);
                case 4:
                    return $instance->$method($args[0], $args[1], $args[2], $args[3]);
                default:
                    return call_user_func_array(array($instance, $method), $args);
            }
        }

        protected static function factory()
        {
            throw new RuntimeException("Facade does not implement factory method.");
        }

        private static function instance()
        {
            $name = static::factory();
            if (false === App::has($name)) {
                throw new Exception("You must define " . $name . " app before use it.");
            }
            return App::make($name);
        }
    }
