<?php
    namespace Thin;

    class Factory extends Customize
    {
        private $__object;

        public function __construct($object)
        {
            $this->__object = $object;
        }

        public function instance()
        {
            return $this->__object;
        }

        public function __call($event, $args)
        {
            if (substr($event, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($event, 3)));
                $key = Inflector::lower($uncamelizeMethod);
                return $this->get($key);
            } elseif(substr($event, 0, 3) == 'set') {
                $value = Arrays::first($args);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($event, 3)));
                $key = Inflector::lower($uncamelizeMethod);
                if ($key == '__object') {
                    throw new Exception("The key $key is protected.");
                }
                return $this->set($key, $value);
            }
            if (true === $this->__has($event)) {
                return $this->__fire($event, $args);
            } else {
                $value = Arrays::first($args);
                if (method_exists($this, $event)) {
                    throw new Exception(
                        "The method $event is a native class' method. Please choose an other name."
                    );
                }
                if (!is_callable($value)) {
                    $closure = function () use ($value) {
                        return $value;
                    };
                    $value = $closure;
                }
                $obj = $this->instance();
                $share = function () use ($obj, $value) {
                    $args   = func_get_args();
                    $args[] = $obj;
                    return call_user_func_array($value, $args);
                };
                $eventable = $this->__event($event, $share);
                return $this;
            }
        }

        public function __isset($key)
        {
            return $this->__has($key);
        }

        public function __get($key)
        {
            if (true === $this->__has($key)) {
                return $this->__fire($key);
            }
            return null;
        }

        public function __set($method, $callable)
        {
            if (method_exists($this, $method)) {
                throw new Exception(
                    "The method $method is a native class' method. Please choose an other name."
                );
            }
            if (!is_callable($callable)) {
                $closure = function () use ($callable) {
                    return $callable;
                };
                $callable = $closure;
            }
            $obj = $this->instance();
            $share = function () use ($obj, $callable) {
                $args   = func_get_args();
                $args[] = $obj;
                return call_user_func_array($callable, $args);
            };
            $eventable = $this->__event($method, $share);
            return $this;
        }
    }
