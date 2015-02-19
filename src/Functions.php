<?php
    namespace Thin;
    use Closure;

    class Functions
    {
        private $values = array();
        private $store = array();
        private static $i = array();

        public static function instance($n = "core")
        {
            $i = isAke(static::$i, $n, null);
            if (empty($i)) {
                $i = static::$i[$n] = new self;
            }
            return $i;
        }

        public function __call($f, $a)
        {
            $f = '_' === $f[0] ? strrev(substr($f, 1, strlen($f))) : $f;
            $c = isAke($this->values, $f, null);
            if (!empty($c)) {
                if ($c instanceof Closure) {
                    return call_user_func_array($c, $a);
                }
            } else {
                if (is_callable($f)) {
                    return call_user_func_array($f, $a);
                } else {
                    $c = isAke($this->store, $f, null);
                    if (!empty($c)) {
                        if (is_callable($c)) {
                            return call_user_func_array($c, $a);
                        }
                    }
                }
            }
            return static::$i;
        }

        public function __set($k, $v)
        {
            if ($v instanceof Closure) {
                $key = '_' === $k[0] ? strrev(substr($k, 1, strlen($k))) : $k;
                $this->values[$key] = $v;
                return $this;
            } else {
                throw new Exception("You must set a valid function.");
            }
        }

        public function __get($k)
        {
            $value = isAke($this->values, $k, null);
            return $value;
        }

        public function __isset($k)
        {
            $value = isAke($this->values, $k, null);
            return !empty($value);
        }

        public function store($n, $f)
        {
            $key = '_' === $n[0] ? strrev(substr($n, 1, strlen($n))) : $n;
            $this->store[$key] = $f;
            return $this;
        }
    }
