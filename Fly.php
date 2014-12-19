<?php
    namespace Thin;

    use Closure;

    class Fly
    {
        public $_data       = [];
        private $_events    = [];

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
                return Instance::make('Fly', $key, with(new self($data)));
            }
        }

        public function event($name, Closure $cb)
        {
            $this->_events[$name] = $cb;
        }

        public function __set($key, $value)
        {
            $this->_data[$key] = $value;
        }

        public function __get($key)
        {
            return isAke($this->_data, $key, null);
        }

        public function __isset($key)
        {
            $check = Utils::token();

            return $check != isake($this->_data, $key, $check);
        }

        public function __unset($key)
        {
            unset($this->_data[$key]);
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

                dd("$func is not a model function of $this->_db.");
            }
        }
    }
