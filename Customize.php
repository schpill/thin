<?php
    namespace Thin;
    use Closure;

    class Customize
    {
        private static $__callables = array();

        public function __event($name, Closure $callable)
        {
            $obj = $this;
            $key = sha1($name);
            if (version_compare(PHP_VERSION, '5.4.0', "<")) {
                $share = function () use ($obj, $callable) {
                    return $callable($obj);
                };
            } else {
                $share = $callable->bindTo($obj);
            }
            self::$__callables[$key] = $share;
            return $this;
        }

        public function __fire($event, $args = array())
        {
            $key = sha1($event);
            $callable = isAke(self::$__callables, $key, null);
            if (is_callable($callable)) {
                return call_user_func_array($callable, $args);
            }
            throw new Exception("The method '$event' is not callable in this class.");
        }

        public function __has($event)
        {
            $key = sha1($event);
            $callable = isAke(self::$__callables, $key, null);
            return is_callable($callable);
        }
    }
