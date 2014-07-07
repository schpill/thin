<?php
    namespace Thin;

    class App
    {
        private static $singletons = array();
        private static $instances = array();
        private static $binds = array();
        private $bag = array();

        public static function bind($name, $closure = null)
        {
            if (true === static::has($name)) {
                throw new Exception("This app $name exists.");
            }
            if (is_null($closure) || !is_callable($closure)) {
                $closure = function () {
                    return new Container;
                };
            }
            $share = function () use ($closure) {
                $args = func_get_args();
                return call_user_func_array($closure, $args);
            };
            static::$binds[$name] = $share;
        }

        public static function singleton($name, $closure = null)
        {
            if (true === static::has($name)) {
                throw new Exception("This app $name exists.");
            }
            if (is_null($closure) || !is_callable($closure)) {
                $closure = function () {
                    return new Container;
                };
            }
            $share = function () use ($closure) {
                $args = func_get_args();
                return call_user_func_array($closure, $args);
            };
            static::$instances[$name] = $share;
        }

        public static function make($name)
        {
            if (false === static::has($name)) {
                throw new Exception("You must define $name app before use it.");
            }

            $args = func_get_args();
            array_shift($args);

            $bind       = isAke(static::$binds, $name, null);
            $singleton  = isAke(static::$instances, $name, null);

            if (!is_null($bind) && is_callable($bind)) {
                return call_user_func_array($bind , $args);
            }

            if (!is_null($singleton) && is_callable($singleton)) {
                $instance = isAke(static::$singletons, $name, null);
                if (!is_null($instance)) {
                    return $instance;
                }
                return static::$singletons[$name] = call_user_func_array($singleton , $args);
            }
        }

        public static function share($name, $closure = null)
        {
            if (false === static::has($name)) {
                throw new Exception("This app $name does not exist.");
            }
            if (is_null($closure) || !is_callable($closure)) {
                $closure = function () {
                    return new Container;
                };
            }

            list($type, $mother) = static::find($name);

            $newClosure = function () use ($mother, $closure) {
                static $object;
                if (null === $object) {
                    $object = $closure($mother);
                }
                return $object;
            };
            return static::$type($name, $newClosure);
        }

        private static function find($name)
        {
            if (true === static::has($name)) {
                $bind       = isAke(static::$binds, $name, null);
                $singleton  = isAke(static::$instances, $name, null);
                return is_null($bind) ? array('singleton', $singleton) : array('bind', $bind);
            }
            $closure = function () {
                return new Container;
            };
            return array('bind', $closure);
        }

        public function has($name)
        {
            $bind       = isAke(static::$binds, $name, null);
            $singleton  = isAke(static::$instances, $name, null);
            return !is_null($bind) || !is_null($singleton);
        }

        public function hasSingleton($name)
        {
            $singleton = isAke(static::$instances, $name, null);
            return !is_null($singleton);
        }

        public function hasBind($name)
        {
            $bind = isAke(static::$binds, $name, null);
            return !is_null($bind);
        }

        public function set($name, $closure)
        {
            $this->bag[$name] = $closure;
            return $this;
        }

        public function get($name, $function = null)
        {
            $closure = isAke($this->bag, $name, null);
            $default = function () {
                return new Container;
            };
            return is_null($closure)
            ? is_null($function)
                ? $default
                : $function
            : $closure;
        }

        public function call($name, $args = array(), $function = null)
        {
            if (!Arrays::is($args)) {
                $args = array($args);
            }
            $closure = $this->get($name, $function);
            return call_user_func_array($closure , $args);
        }
    }
