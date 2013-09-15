<?php
    /**
     * Object class
     *
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Object extends \ArrayObject implements \ArrayAccess
    {
        public $_args;
        public $_datas = array();

        public function __construct()
        {
            $args   = func_get_args();
            $nbArgs = func_num_args();
            if (1 == $nbArgs && (is_array(current($args)) || is_object(current($args)))) {
                if (is_array(current($args))) {
                    $this->populate(current($args));
                } elseif (is_object(current($args))) {
                    $array = (array) current($args);
                    $this->populate($array);
                }
            }
            $this->_args = $args;
            $this->_nameClass = Inflector::lower(get_class($this));
            return $this;
        }

        public function __call($func, $argv)
        {
            if (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (isset($this->$var)) {
                    return $this->$var;
                } else {
                    return null;
                }
            } elseif (substr($func, 0, 3) == 'has') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                return isset($this->$var) && !empty($this->$var);
            } elseif (substr($func, 0, 3) == 'set') {
                $value = current($argv);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (!empty($var)) {
                    $this->$var = $value;
                }
                return $this;
            }
            if (!is_callable($func) || substr($func, 0, 6) !== 'array_' || substr($func, 0, 3) !== 'set' || substr($func, 0, 3) !== 'get') {
                throw new \BadMethodCallException(__class__ . ' => ' . $func);
            }
            return call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
        }

        public function __invoke($key, $value)
        {
            $this->$key = $value;
            return $this;
        }

        public function populate(array $datas, $namespace = null)
        {
            if (null !== $namespace) {
                if (!isset($this->$namespace)) {
                    $this->$namespace = array();
                }
                foreach ($datas as $k => $v) {
                    if (is_array($k)) {
                        $this->populate($k, $namespace);
                    } else {
                        $this->$namespace = array_merge($this->$namespace, array($k => $v));
                    }
                }
            } else {
                foreach ($datas as $k => $v) {
                    $this->$k = $v;
                }
            }
            return $this;
        }

        public function toArray()
        {
            return $this->getArrayCopy();
        }
    }
