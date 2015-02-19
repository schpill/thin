<?php
    namespace Thin;
    use Closure;

    class Customize
    {
        private static $__callables = array();
        private $__values = array();

        public function __event($name, Closure $callable)
        {
            if (method_exists($this, $name)) {
                throw new Exception("The method $name is a native class' method. Please choose an other name.");
            }

            $obj = $this;
            $key = sha1($name);
            self::$__callables[$key] = $callable;

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

        public function set($key, $value)
        {
            $this->__values[$key] = $value;

            return $this;
        }

        public function forget($key)
        {
            $this->__values[$key] = null;

            return $this;
        }

        public function has($key)
        {
            $value = isAke($this->__values, $key, null);

            return !is_null($value);
        }

        public function get($key, $default = null)
        {
            return isAke($this->__values, $key, $default);
        }

        public function reset()
        {
            $this->__values = array();
            self::$__callables = array();

            return $this;
        }

    }
