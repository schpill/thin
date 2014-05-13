<?php
    namespace Thin;

    class Context extends Customize
    {
        private static $instances   = array();

        public static function instance($ns = 'core')
        {
            $i = isAke(static::$instances, $ns, null);
            if (is_null($i)) {
                $i = static::$instances[$ns] = new self;
            }
            return $i;
        }

        public function __call($event, $args)
        {
            if (true === $this->__has($event)) {
                return $this->__fire($event, $args);
            } else {
                $value = Arrays::first($args);
                if (is_callable($value)) {
                    $eventable = $this->__event($event, $value);
                } else {
                    $set = function () use ($value) {
                        return $value;
                    };
                    $eventable =  $this->__event($event, $set);
                }
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

        public function __set($key, $value)
        {
            if (is_callable($value)) {
                $eventable = $this->__event($key, $value);
            } else {
                $set = function () use ($value) {
                    return $value;
                };
                $eventable = $this->__event($key, $set);
            }
            return $this;
        }
    }
