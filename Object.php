<?php
    /**
     * Object class
     *
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Object extends \ArrayObject implements \ArrayAccess
    {
        public $_fields = array();

        public function __construct()
        {
            $args   = func_get_args();
            $nbArgs = func_num_args();
            if (1 == $nbArgs && (is_array(current($args)) || is_object(current($args)))) {
                if (Arrays::isArray(current($args))) {
                    $this->populate(current($args));
                } elseif (is_object(current($args))) {
                    $array = (array) current($args);
                    $this->populate($array);
                }
            }
            $this->_nameClass = Inflector::lower(get_class($this));
            return $this;
        }

        public function save()
        {
            if (isset($this->thin_type)) {
                $type = $this->thin_type;
                $data = array();
                if (ake($type, Data::$_fields)) {
                    $fields = Data::$_fields[$type];
                    foreach ($fields as $field => $infp) {
                        $data[$field] = (isset($this->$field)) ? $this->$field : null;
                    }
                    if (isset($this->id)) {
                        $newId = Data::edit($type, $this->id, $data);
                    } else {
                        $newId = Data::add($type, $data);
                    }
                    return Data::getById($type, $newId);
                }
            }
            return $this;
        }

        public function delete()
        {
            if (isset($this->thin_type)) {
                $type = $this->thin_type;
                if (ake($type, Data::$_fields)) {
                    if (isset($this->id)) {
                        $del = Data::delete($type, $this->id);
                    }
                }
                $object = new self;
                $object->setThinType($type);
            } else {
                $object = new self;
            }
            return $object;
        }

        public function erase()
        {
            return $this->delete();
        }

        public function __call($func, $argv)
        {
            if (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (isset($this->$var)) {
                    if (isset($this->thin_type)) {
                        $type = $this->thin_type;
                        $settings = ake($type, Data::$_settings) ? Data::$_settings[$type] : array();
                        if (ake('relationships', $settings)) {
                            if (ake($var, $settings['relationships'])) {
                                return Data::getById($var, $this->$var);
                            }
                        }
                    }
                    return $this->$var;
                } else {
                    if (isset($this->thin_type)) {
                        $type = $this->thin_type;
                        $settings = ake($type, Data::$_settings) ? Data::$_settings[$type] : array();
                        if (ake($var, $settings['relationships']) && 's' == $var[strlen($var) - 1]) {
                            if (ake($var, $settings['relationships'])) {
                                $res = Data::query(substr($var, 0, -1), "$type = " . $this->id);
                                $collection = array();
                                if (count($res)) {
                                    foreach ($res as $row) {
                                        $obj = Data::getObject($row);
                                        $collection[] = $obj;
                                    }
                                }
                                return (1 == count($collection)) ? current($collection) : $collection;
                            }
                        } elseif (ake('defaultValues', $settings)) {
                            if (Arrays::isArray($settings['defaultValues'])) {
                                if(ake($this->$var, $settings['defaultValues'])) {
                                    return $settings['defaultValues'][$this->$var];
                                }
                            }
                        }
                    }
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
                    if (!Arrays::inArray($var, $this->_fields)) {
                        $this->_fields[] = $var;
                    }
                }
                return $this;
            }
            if (Arrays::inArray($func, $this->_fields)) {
                if ($this->$func instanceof \Closure) {
                    return call_user_func_array($this->$func, $argv);
                }
            }
            if (!is_callable($func) || substr($func, 0, 6) !== 'array_' || substr($func, 0, 3) !== 'set' || substr($func, 0, 3) !== 'get' || substr($func, 0, 3) !== 'has') {
                throw new \BadMethodCallException(__class__ . ' => ' . $func);
            }
            return call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
        }

        public function __invoke($key, $value)
        {
            $this->$key = $value;
            return $this;
        }

        public function __get($key)
        {
            $array = $this->values;
            if (count($array)) {
                foreach ($array as $k => $v) {
                    if (!Arrays::inArray($k, $this->_fields)) {
                        $this->_fields[] = $k;
                    }
                    $this->$k = $v;
                }
            }
            if (isset($this->$key)) {
                if (isset($this->thin_type)) {
                    $type = $this->thin_type;
                    $settings = ake($type, Data::$_settings) ? Data::$_settings[$type] : array();
                    if (ake('relationships', $settings)) {
                        if (ake($key, $settings['relationships']) && 's' != $key[strlen($key) - 1]) {
                            return Data::getById($key, $this->$key);
                        }
                        if (ake($key, $settings['relationships']) && 's' == $key[strlen($key) - 1]) {
                            return Data::query(substr($key, 0, -1), "$type = " . $this->id);
                        }
                    }
                }
                return $this->$key;
            }
            return null;
        }

        public function __set($key, $value)
        {
            if (empty($key)) {
                return;
            }
            $this->$key = $value;
            $this[$key] = $value;
            if (!Arrays::inArray($key, $this->_fields)) {
                $this->_fields[] = $key;
            }
            return $this;
        }

        public function offsetSet($key, $value)
        {
            $this->$key = $value;
            if (!Arrays::inArray($key, $this->_fields)) {
                $this->_fields[] = $key;
            }
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
                    if (!Arrays::inArray($k, $this->_fields)) {
                        $this->_fields[] = $k;
                    }
                }
            }
            return $this;
        }

        public function get($var)
        {
            if (isset($this->$var)) {
                if (isset($this->thin_type)) {
                    $type = $this->thin_type;
                    $settings = ake($type, Data::$_settings) ? Data::$_settings[$type] : array();
                    if (ake('relationships', $settings)) {
                        if (ake($var, $settings['relationships'])) {
                            return Data::getById($var, $this->$var);
                        }
                    }
                }
                return $this->$var;
            } else {
                if (isset($this->thin_type)) {
                    $type = $this->thin_type;
                    $settings = ake($type, Data::$_settings) ? Data::$_settings[$type] : array();
                    if (ake($var, $settings['relationships']) && 's' == $var[strlen($var) - 1]) {
                        if (ake($var, $settings['relationships'])) {
                            $res = Data::query(substr($var, 0, -1), "$type = " . $this->id);
                            $collection = array();
                            if (count($res)) {
                                foreach ($res as $row) {
                                    $obj = Data::getObject($row);
                                    $collection[] = $obj;
                                }
                            }
                            return (1 == count($collection)) ? current($collection) : $collection;
                        }
                    }
                }
            }
            return null;
        }

        public function set($key, $value)
        {
            return $this->put($key, $value);
        }

        public function put($key, $value)
        {
            $this->$key = $value;
            if (!Arrays::inArray($key, $this->_fields)) {
                $this->_fields[] = $key;
            }
            return $this;
        }

        public function forget($key)
        {
            if (isset($this->$key)) {
                unset($this->$key);
                if(($arrayKey = array_search($key, $this->_fields)) !== false) {
                    unset($this->_fields[$arrayKey]);
                }
            }
            return $this;
        }

        public function toArray()
        {
            $collection = array();
            foreach ($this->_fields as $field) {
                $collection[$field] = $this->$field;
            }
            foreach ($this->values as $field => $value) {
                $collection[$field] = $value;
            }
            return $collection;
        }

        public function search(array $search)
        {
            if (!count($this->_fields) || !count($search)) {
                return false;
            }
            foreach($search as $key => $value) {
                if (!Arrays::inArray($key, $this->_fields)) {
                    return false;
                }
                if($this->$key <> $value) {
                    return false;
                }
            }
            return true;
        }

        private function _analyze()
        {
            $values = $this->values();
            foreach ($values as $k => $v) {
                if (!Arrays::inArray($k, $this->_fields)) {
                    $this->$k = $v;
                }
            }
            return $this;
        }

        public function values()
        {
            return $this->values;
        }

        public function fields()
        {
            if (isset($this->thin_type)) {
                $type = $this->thin_type;

                if (ake($type, Data::$_fields)) {
                    $fields = array();
                    $dataFields = Data::$_fields[$type];
                    foreach ($dataFields as $dataField => $info) {
                        $fields[] = $dataField;
                    }
                    return $fields;
                }
            }
            return $this->_fields;
        }
    }
