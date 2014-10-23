<?php
    namespace Thin;
    use Closure;
    use \Thin\Database\Collection;

    class App
    {
        private $__events   = [];
        private $__bag      = [];
        private $__token;

        public function __construct($app = 'core')
        {
            $this->__token = sha1($app . Utils::token());
        }

        public static function instance($app = 'core')
        {
            $key    = sha1($app);
            $has    = Instance::has('App', $key);

            if (true === $has) {
                return Instance::get('App', $key);
            } else {
                return Instance::make('App', $key, new self($app));
            }
        }

        public function get($key, $default = null)
        {
            return isAke($this->__bag, $key, $default);
        }

        public function set($key, $value)
        {
            $this->__bag[$key] = $value;

            return $this;
        }

        public function has($key)
        {
            return isAke($this->__bag, $key, 'dummy') != 'dummy';
        }

        public function forget($key)
        {
            if (true === $this->has($key)) {
                unset($this->__bag[$key]);
            }

            return $this;
        }

        public function event($id, Closure $closure)
        {
            $this->__events[sha1($id . $this->__token)] = $closure;

            return $this;
        }

        public function __get($key)
        {
            return $this->get($key);
        }

        public function __set($key, $value)
        {
            $this->set($key, $value);
        }

        public function __isset($key)
        {
            return $this->has($key);
        }

        public function __unset($key)
        {
            return $this->forget($key);
        }

        public function __call($method, $args)
        {
            if (substr($method, 0, strlen('get')) == 'get' && strlen($method) > strlen('get')) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, strlen('get'))));
                $field = Inflector::lower($uncamelizeMethod);
                $default = count($args) ? reset($args) : null;

                return $this->get($field, $default);
            } elseif (substr($method, 0, strlen('set')) == 'set' && strlen($method) > strlen('set')) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, strlen('get'))));
                $field = Inflector::lower($uncamelizeMethod);
                $value = count($args) ? reset($args) : null;
                $this->set($field, $value);

                return $this;
            } elseif (substr($method, 0, strlen('has')) == 'has' && strlen($method) > strlen('has')) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, strlen('get'))));
                $field = Inflector::lower($uncamelizeMethod);

                return $this->has($field);
            } elseif (substr($method, 0, strlen('forget')) == 'forget' && strlen($method) > strlen('forget')) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, strlen('get'))));
                $field = Inflector::lower($uncamelizeMethod);

                return $this->forget($field);
            } elseif (substr($method, 0, strlen('remove')) == 'remove' && strlen($method) > strlen('remove')) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, strlen('get'))));
                $field = Inflector::lower($uncamelizeMethod);

                return $this->forget($field);
            } else {
                if (isset($this->__token)) {
                    $id = sha1($method . $this->__token);

                    $cb = isAke($this->__events, $id, false);

                    if (false !== $cb) {
                        return call_user_func_array($cb, $args);
                    }
                }
            }

            throw new Exception("Method '$method' does not exist.");
        }

        public function toArray($isNumericIndex = true, $itemToArray = false)
        {
            $array = [];

            foreach ($this->_items as $item) {
                if (false === $isNumericIndex) {
                    if (true === $itemToArray) {
                        if ($item instanceof self) {
                            $item = $item->toArray($isNumericIndex, $itemToArray);
                        }
                    }
                } else {
                    if (true === $itemToArray) {
                        if ($item instanceof self) {
                            $item = $item->toArray($isNumericIndex, $itemToArray);
                        }
                    }
                }

                $array[] = $item;
            }

            return $array;
        }

        public function toCollection()
        {
            return with(new Collection($this->toArray()));
        }

        public function toJson($render = false)
        {
            $json = json_encode($this->toArray(true, true));

            if (false === $render) {
                return $json;
            } else {
                header('content-type: application/json; charset=utf-8');

                die($json);
            }
        }

        public static function getMethodsCalled()
        {
            $bt = debug_backtrace();

            array_shift($bt);

            dd($bt);
        }
    }
