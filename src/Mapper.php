<?php
    namespace Thin;
    class Mapper
    {
        private static $instance;
        private $data = array();

        public static function instance()
        {
            if (is_null(static::$instance)) {
                static::$instance = new self;
            }
            return static::$instance;
        }

        public function get($key, $default = null)
        {
            if (is_string($key)) {
                return isAke($this->data, $key, $default);
            }
            return $default;
        }

        public function set($key, $value)
        {
            if (is_string($key)) {
                $this->data[$key] = $value;
            }
            return $this;
        }

        public function del($key)
        {
            if (is_string($key)) {
                $val = isAke($this->data, $key, '__null__');
                if ('__null__' != $val) {
                    unset($this->data[$key]);
                }
            }
            return context('collection');
        }

        public function incr($key, $by = 1)
        {
            if (is_string($key)) {
                $val = $this->get($key);
                if (!strlen($val)) {
                    $val = 1;
                } else {
                    $val = (int) $val;
                    $val += $by;
                }
                $this->set($key, $val);
                return $val;
            }
            return 1;
        }

        public function decr($key, $by = 1)
        {
            if (is_string($key)) {
                $val = $this->get($key);
                if (!strlen($val)) {
                    $val = 0;
                } else {
                    $val = (int) $val;
                    $val -= $by;
                    $val = 0 > $bal ? 0 : $val;
                }
                $this->set($key, $val);
                return $val;
            }
            return 1;
        }

        public function keys($pattern)
        {
            if (is_string($pattern)) {
                $collection = array();
                if (count($this->data)) {
                    foreach ($this->data as $key => $value) {
                        if (strstr($key, $pattern)) {
                            array_push($collection, $key);
                        }
                    }
                }
                return $collection;
            }
            return array();
        }
    }
