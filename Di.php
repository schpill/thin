<?php
    namespace Thin;
    use Closure;

    class Di
    {
        private static $__callables = array();
        private static $__values = array();

        public static function __callStatic($event, $args)
        {
            if (substr($event, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($event, 3)));
                $key = Inflector::lower($uncamelizeMethod);
                return static::get($key);
            } elseif(substr($event, 0, 3) == 'set') {
                $value = Arrays::first($args);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($event, 3)));
                $key = Inflector::lower($uncamelizeMethod);
                return static::set($key, $value);
            }
            if (true === static::__has($event)) {
                return static::__fire($event, $args);
            } else {
                $value = Arrays::first($args);
                if (is_callable($value)) {
                    $eventable = static::__event($event, $value);
                } else {
                    $set = function () use ($value) {
                        return $value;
                    };
                    $eventable =  static::__event($event, $set);
                }
            }
        }

        public static function __event($name, Closure $callable)
        {
            $key = sha1($name);
            static::$__callables[$key] = $callable;
        }

        public static function __fire($event, $args = array())
        {
            $key = sha1($event);
            $callable = isAke(static::$__callables, $key, null);
            if (is_callable($callable)) {
                return call_user_func_array($callable, $args);
            }
            throw new Exception("The method '$event' is not callable in this class.");
        }

        public static function __has($event)
        {
            $key = sha1($event);
            $callable = isAke(static::$__callables, $key, null);
            return is_callable($callable);
        }

        public static function set($key, $value)
        {
            static::$__values[$key] = $value;
        }

        public static function forget($key)
        {
            static::$__values[$key] = null;
        }

        public static function has($key)
        {
            $value = isAke(static::$__values, $key, null);
            return !is_null($value);
        }

        public static function get($key, $default = null)
        {
            return isAke(static::$__values, $key, $default);
        }

        public static function reset()
        {
            static::$__values = array();
            static::$__callables = array();
        }
    }
