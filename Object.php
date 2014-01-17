<?php
    /**
     * Object class
     *
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Object extends \ArrayObject implements \ArrayAccess
    {
        public $_fields         = array();
        public $_closures       = array();

        public function __construct()
        {
            $args   = func_get_args();
            $nbArgs = func_num_args();
            if (1 == $nbArgs && (Arrays::isArray(Arrays::first($args)) || is_object(Arrays::first($args)))) {
                if (Arrays::isArray(Arrays::first($args))) {
                    $this->populate(Arrays::first($args));
                } elseif (is_object(Arrays::first($args))) {
                    $array = (array) Arrays::first($args);
                    $this->populate($array);
                }
            }
            $this->_nameClass = Inflector::lower(get_class($this));
            return $this;
        }

        public function closure($name, \Closure $closure)
        {
            $this->_closures[$name] = $closure;
            return $this;
        }

        public function save()
        {
            if (isset($this->thin_type)) {
                $type = $this->thin_type;
                $data = array();
                if (ake($type, Data::$_fields)) {
                    $fields = Data::$_fields[$type];
                    foreach ($fields as $field => $info) {
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
                        $relationships = ake('relationships', $settings) ? $settings['relationships'] : array();
                        if (ake($var, $relationships) && 's' == $var[strlen($var) - 1]) {
                            if (ake($var, $relationships)) {
                                $res = Data::query(substr($var, 0, -1), "$type = " . $this->id);
                                $collection = array();
                                if (count($res)) {
                                    foreach ($res as $row) {
                                        $obj = Data::getObject($row);
                                        $collection[] = $obj;
                                    }
                                }
                                return (1 == count($collection)) ? Arrays::first($collection) : $collection;
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
                $value = Arrays::first($argv);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (!empty($var)) {
                    if (isset($this->thin_type)) {
                        $fields = ake($this->thin_type, Data::$_fields) ? Data::$_fields[$this->thin_type] : array();
                        if(!ake($var, $fields)) {
                            throw new Exception($var . ' is not defined in the model => ' . $this->_fields);
                        } else {
                            $settingsField = $fields[$var];
                            if (ake('checkValue', $settingsField)) {
                                $functionCheck = $settingsField['checkValue'];
                                $value = $functionCheck($value);
                            }
                            if (is_object($value)) {
                                if (isset($value->thin_type)) {
                                    if ($value->thin_type == $var) {
                                        $value = $value->id;
                                    }
                                }
                            }
                        }
                    }
                    $this->$var = $value;
                    if (!Arrays::inArray($var, $this->_fields)) {
                        $this->_fields[] = $var;
                    }
                    if (isset($this->is_thin_object)) {
                        $name           = $this->is_thin_object;
                        $objects        = Utils::get('thinObjects');
                        $objects[$name] = $this;
                        Utils::set('thinObjects', $objects);
                    }
                    if (isset($this->is_app)) {
                        if (true === $this->is_app) {
                            Utils::set('ThinAppContainer', $this);
                        }
                    }
                }
                return $this;
            } elseif (substr($func, 0, 3) == 'add') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var                = Inflector::lower($uncamelizeMethod) . 's';
                $value              = Arrays::first($argv);
                if (!isset($this->$var)) {
                    $this->$var = array();
                }
                if (!Arrays::isArray($this->$var)) {
                    $this->$var = array();
                }
                array_push($this->$var, $value);
                return $this;
            } elseif (substr($func, 0, 6) == 'remove') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, 6)));
                $var                = Inflector::lower($uncamelizeMethod) . 's';
                $value              = Arrays::first($argv);
                if (isset($this->$var)) {
                    if (Arrays::isArray($this->$var)) {
                        if (count($this->$var)) {
                            $remove = false;
                            foreach ($this->$var as $key => $tmpValue) {
                                $comp = md5(serialize($value)) == md5(serialize($tmpValue));
                                if (true === $comp) {
                                    $remove = true;
                                    break;
                                }
                            }
                            if (true === $remove) {
                                unset($this->$var[$key]);
                            }
                        }
                    }
                }
                return $this;
            }

            if (Arrays::inArray($func, $this->_fields)) {
                if ($this->$func instanceof \Closure) {
                    return call_user_func_array($this->$func, $argv);
                }
            }
            if (ake($func, $this->_closures)) {
                if ($this->_closures[$func] instanceof \Closure) {
                    return call_user_func_array($this->_closures[$func] , $argv);
                }
            }

            if (!is_callable($func) || substr($func, 0, 6) !== 'array_' || substr($func, 0, 3) !== 'set' || substr($func, 0, 3) !== 'get' || substr($func, 0, 3) !== 'has' || substr($func, 0, 3) !== 'add' || substr($func, 0, 6) !== 'remove') {
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
                            return (1 == count($collection)) ? Arrays::first($collection) : $collection;
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
