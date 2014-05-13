<?php
    namespace Thin;

    class Conf extends Customize
    {
        private static $instances   = array();
        private $values             = array();

        public static function instance($ns = 'core')
        {
            $i = isAke(static::$instances, $ns, null);
            if (is_null($i)) {
                $i = static::$instances[$ns] = new self;
            }
            return $i;
        }

        public function __isset($key)
        {
            $val = arrayGet($this->values, $this->format($key));
            return strlen($val) > 0 ? true : false;
        }

        public function __get($key)
        {
            return arrayGet($this->values, $this->format($key));
        }

        public function __set($key, $value)
        {
            $this->values = arraySet($this->values, $this->format($key), $value);
            return $this;
        }

        public function __call($method, $args)
        {
            if (true === $this->__has($method)) {
                return $this->__fire($method, $args);
            }
            $reverse = strrev($method);
            $last = $reverse{0};
            if ('s' == $last) {
                if (!count($args)) {
                    return isAke($this->values, $method);
                } else {
                    $this->values[$method] = !Arrays::is($this->values[$method]) ? array() : $this->values[$method];
                    foreach ($args as $arg) {
                        array_push($this->values[$method], $arg);
                    }
                }
                return $this;
            } else {
                $method .= 's';
                if (!count($args)) {
                    $val = isAke($this->values, $method);
                    return count($val) ? Arrays::first($val) : null;
                } else {
                    $this->values[$method] = !Arrays::is($this->values[$method]) ? array() : $this->values[$method];
                    foreach ($args as $arg) {
                        array_push($this->values[$method], $arg);
                    }
                }
                return $this;
            }
        }

        private function format($key)
        {
            return str_replace(array('_', '-'), array('.', '.'), $key);
        }
    }
