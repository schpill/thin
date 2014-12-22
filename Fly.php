<?php
    namespace Thin;

    use ArrayObject;
    use ArrayAccess;
    use Countable;
    use IteratorAggregate;
    use Closure;

    class Fly extends ArrayObject implements ArrayAccess, Countable, IteratorAggregate
    {
        public $_data = [], $_events = [];

        public function __construct($data = [])
        {
            $this->_data = $data;
        }

        public static function instance($name, $data = [])
        {
            $key    = sha1('Fly.' . $name);
            $has    = Instance::has('Fly', $key);

            if (true === $has) {
                return Instance::get('Fly', $key);
            } else {
                return Instance::make('Fly', $key, new self($data));
            }
        }

        public function event($name, Closure $cb)
        {
            $this->_events[$name] = $cb;

            return $this;
        }

        public function __set($key, $value)
        {
            if (is_callable($value)) {
                return $this->event($key, $value);
            }

            $this->_data[$key] = $value;

            return $this;
        }

        public function offsetSet($key, $value)
        {
            if (is_callable($value)) {
                return $this->event($key, $value);
            }

            $this->_data[$key] = $value;

            return $this;
        }

        public function __get($key)
        {
            return isAke($this->_data, $key, null);
        }

        public function offsetGet($key)
        {
            return isAke($this->_data, $key, null);
        }

        public function __isset($key)
        {
            $check = Utils::token();

            return $check != isake($this->_data, $key, $check);
        }

        public function offsetExists($key)
        {
            $check = Utils::token();

            return $check != isake($this->_data, $key, $check);
        }

        public function hasEvent($key)
        {
            $check = Utils::token();

            return $check != isake($this->_events, $key, $check);
        }

        public function __unset($key)
        {
            unset($this->_data[$key]);
        }

        public function offsetUnset($key)
        {
            unset($this->_data[$key]);
        }

        public function register($provider, $args = [])
        {
            $args = array_merge([$this], $args);
            call_user_func_array([$provider, 'register'], $args);

            return $this;
        }

        public function __call($func, $args)
        {
            if (substr($func, 0, strlen('get')) == 'get') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('get'))));
                $field              = Inflector::lower($uncamelizeMethod);

                $default = count($args) == 1 ? Arrays::first($args) : null;

                return isAke($this->_data, $field, $default);
            } elseif (substr($func, 0, strlen('set')) == 'set') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('set'))));
                $field              = Inflector::lower($uncamelizeMethod);

                if (!empty($args)) {
                    $val = Arrays::first($args);
                } else {
                    $val = null;
                }

                $this->_data[$field] = $val;

                return $this;
            } else {
                $cb = isake($this->_events, $func, false);

                if (false !== $cb) {
                    if ($cb instanceof Closure) {
                        return call_user_func_array($cb, $args);
                    }
                }

                dd("$func is not a model function of this object.");
            }
        }
    }
