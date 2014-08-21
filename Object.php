<?php
    /**
     * Object class
     *
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    use SQLite3;

    class Object extends \ArrayObject implements \ArrayAccess, \Countable, \IteratorAggregate
    {
        public $_fields         = array();
        public $_closures       = array();

        public function __construct()
        {
            $args   = func_get_args();
            $nbArgs = func_num_args();

            if (1 == $nbArgs && (Arrays::is(Arrays::first($args)) || is_object(Arrays::first($args)))) {
                if (Arrays::is(Arrays::first($args))) {
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
            if (isset($this->_token)) {
                $id = sha1('save' . $this->_token);

                if (Arrays::is($this->values)) {
                    if (Arrays::exists($id, $this->values)) {
                        return call_user_func_array($this->values[$id], func_get_args());
                    }
                }
            }

            if(isset($this->db_instance)) {
                return $this->db_instance->save($this);
            }

            if(isset($this->thin_litedb)) {
                return $this->thin_litedb->save($this);
            }

            if (isset($this->thin_kv)) {
                $db = new Keyvalue($this->thin_kv);

                return $db->save($this->toArray());
            } elseif (isset($this->thin_type)) {
                $type = $this->thin_type;
                Data::getModel($type);
                $data = array();

                if (Arrays::exists($type, Data::$_fields)) {
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
            } elseif(Arrays::exists('save', $this->_closures)) {
                $this->_closures['save']($this);
            }

            return $this;
        }

        public function delete()
        {
            if (isset($this->_token)) {
                $id = sha1('delete' . $this->_token);

                if (Arrays::is($this->values)) {
                    if (Arrays::exists($id, $this->values)) {
                        return call_user_func_array($this->values[$id], func_get_args());
                    }
                }
            }

            if(isset($this->db_instance)) {
                return $this->db_instance->delete($this);
            }

            if(isset($this->thin_litedb)) {
                return $this->thin_litedb->delete($this);
            }

            if (isset($this->thin_type)) {
                $type = $this->thin_type;
                Data::getModel($type);

                if (Arrays::exists($type, Data::$_fields)) {
                    if (isset($this->id)) {
                        $del = Data::delete($type, $this->id);
                    }
                }

                $object = new self;
                $object->setThinType($type);
            } elseif(Arrays::exists('delete', $this->_closures)) {
                $this->_closures['delete']($this);
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
            $key = sha1('orm' . $this->_token);
            $orm = isAke($this->values, $key, false);

            if (substr($func, 0, 4) == 'link' && false !== $orm) {
                $value = Arrays::first($argv);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 4)));
                $var = Inflector::lower($uncamelizeMethod);

                if (!empty($var)) {
                    $var = setter($var . '_id');
                    $this->$var($value->id());
                    return $this;
                }
            } elseif (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);

                if (isset($this->$var)) {
                    if (isset($this->thin_type)) {
                        $type = $this->thin_type;
                        Data::getModel($type);
                        $settings = Arrays::exists($type, Data::$_settings) ? Data::$_settings[$type] : array();

                        if (Arrays::exists('relationships', $settings)) {
                            if (Arrays::exists($var, $settings['relationships'])) {
                                return Data::getById($var, $this->$var);
                            }
                        }
                    }

                    if (Arrays::is($this->$var) && count($argv) == 1) {
                        $o = new self;
                        $getter = getter(Arrays::first($argv));
                        $o->populate($this->$var);

                        return $o->$getter();
                    }

                    if ($this->$var instanceof \Closure) {
                        if(is_callable($this->$var) && count($argv)) {
                            return call_user_func_array($this->$var, $argv);
                        }
                    }

                    return count($argv) && is_null($this->$var) ? Arrays::first($argv) : $this->$var;
                } else {
                    if (isset($this->db_instance)) {
                        return $this->db_instance->getValue($this, $var);
                    }

                    if (isset($this->thin_type)) {
                        $type = $this->thin_type;
                        Data::getModel($type);
                        $settings = Arrays::exists($type, Data::$_settings) ? Data::$_settings[$type] : array();
                        $relationships = Arrays::exists('relationships', $settings) ? $settings['relationships'] : array();

                        if (Arrays::exists($var, $relationships) && 's' == $var[strlen($var) - 1]) {
                            if (Arrays::exists($var, $relationships)) {
                                $res = dm(substr($var, 0, -1))->where("$type = " . $this->id)->get();
                                $collection = array();

                                if (count($res)) {
                                    foreach ($res as $obj) {
                                        array_push($collection, $obj);
                                    }
                                }

                                return $collection;
                            }
                        } elseif (Arrays::exists('defaultValues', $settings)) {
                            if (Arrays::is($settings['defaultValues'])) {
                                if(Arrays::exists($this->$var, $settings['defaultValues'])) {
                                    return $settings['defaultValues'][$this->$var];
                                }
                            }
                        }
                    }

                    if (count($argv) == 1) {
                        return Arrays::first($argv);
                    }

                    return null;
                }
            } elseif (substr($func, 0, 3) == 'has') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);

                if (isset($this->$var)) {
                    return !empty($this->$var);
                } elseif(isset($this->db_instance)) {
                    return $this->db_instance->hasValue($this, $var);
                }
            } elseif (substr($func, 0, 3) == 'set') {
                $value = Arrays::first($argv);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);

                if (!empty($var)) {
                    if (isset($this->thin_type)) {
                        Data::getModel($this->thin_type);
                        $fields = Arrays::exists($this->thin_type, Data::$_fields) ? Data::$_fields[$this->thin_type] : array();

                        if(!Arrays::exists($var, $fields)) {
                            throw new Exception($var . ' is not defined in the model => ' . $this->thin_type);
                        } else {
                            $settingsField = $fields[$var];

                            if (Arrays::exists('checkValue', $settingsField)) {
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

                    if (!Arrays::in($var, $this->_fields)) {
                        $this->_fields[] = $var;
                    }

                    if (isset($this->is_thin_object)) {
                        $name           = $this->is_thin_object;
                        $objects        = Utils::get('thinObjects');
                        $this->values = null;
                        $objects[$name] = $this;
                        Utils::set('thinObjects', $objects);
                    }

                    if (isset($this->is_app)) {
                        if (true === $this->is_app) {
                            Utils::set('ThinAppContainer', $this);
                        }
                    }
                } elseif(isset($this->db_instance)) {
                    return $this->db_instance->setValue($this, $var, $value);
                }

                return $this;
            } elseif (substr($func, 0, 3) == 'add') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var                = Inflector::lower($uncamelizeMethod) . 's';
                $value              = Arrays::first($argv);

                if (!isset($this->$var)) {
                    $this->$var = array();
                }

                if (!Arrays::is($this->$var)) {
                    $this->$var = array();
                }

                array_push($this->$var, $value);

                return $this;
            } elseif (substr($func, 0, 6) == 'remove') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, 6)));
                $var                = Inflector::lower($uncamelizeMethod) . 's';
                $value              = Arrays::first($argv);

                if (isset($this->$var)) {
                    if (Arrays::is($this->$var)) {
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

            if (Arrays::in($func, $this->_fields)) {
                if ($this->$func instanceof \Closure) {
                    return call_user_func_array($this->$func, $argv);
                }
            }

            if (Arrays::exists($func, $this->_closures)) {
                if ($this->_closures[$func] instanceof \Closure) {
                    return call_user_func_array($this->_closures[$func] , $argv);
                }
            }

            if (isset($this->_token)) {
                $id = sha1($func . $this->_token);

                if (Arrays::is($this->values)) {
                    if (Arrays::exists($id, $this->values)) {
                        return call_user_func_array($this->values[$id], $argv);
                    }
                }
            }

            if (true === hasEvent($func)) {
                array_push($argv, $this);

                return fire($func, $argv);
            }

            if (!is_callable($func)
                || substr($func, 0, 6) !== 'array_'
                || substr($func, 0, 3) !== 'set'
                || substr($func, 0, 3) !== 'get'
                || substr($func, 0, 3) !== 'has'
                || substr($func, 0, 3) !== 'add'
                || substr($func, 0, 6) !== 'remove'
            ) {
                $callable = strrev(repl('_', '', $func));

                if (!is_callable($callable)) {
                    if(method_exists($this, $callable)) {
                        return call_user_func_array(array($this, $callable), $argv);
                    }
                } else {
                    return call_user_func_array($callable, $argv);
                }

                if(isset($this->thin_litedb)) {
                    $closure = isAke($this->thin_litedb->closures, $func);

                    if (!empty($closure) && $closure instanceof \Closure) {
                        return $closure($this);
                    }
                }

                if(isset($this->db_instance)) {
                    return $this->db_instance->$func($this, $var, $value);
                    call_user_func_array(array($this->db_instance, $func), array_merge(array($this), $argv));
                }

                if (false !== $orm) {
                    $db     = call_user_func_array($orm, array());
                    $fields = array_keys($db->map['fields']);

                    if (Arrays::in($func, $fields)) {
                        if (!count($argv)) return $this->$func;
                        else {
                            $setter = setter($func);
                            $this->$setter(Arrays::first($argv));

                            return $this;
                        }
                    }

                    $tab    = str_split($func);
                    $many   = false;

                    if (Arrays::last($tab) == 's') {
                        array_pop($tab);
                        $table  = implode('', $tab);
                        $many   = true;
                    } else {
                        $table  = $func;
                    }

                    $object = count($argv) == 1 ? Arrays::first($argv) : true;
                    $model  = model($table);

                    return true === $many
                    ? $model->where($db->table . '_id = ' . $this->id())->exec($object)
                    : $model->where($db->table . '_id = ' . $this->id())->first($object);
                }

                return null;
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
            $array = isset($this->values) ? $this->values : array();

            if (count($array)) {
                foreach ($array as $k => $v) {
                    if (!Arrays::in($k, $this->_fields)) {
                        $this->_fields[] = $k;
                    }

                    $this->$k = $v;
                }
            }
            if (isset($this->$key)) {
                if (isset($this->thin_type)) {
                    $type = $this->thin_type;
                    Data::getModel($this->thin_type);
                    $settings = Arrays::exists($type, Data::$_settings) ? Data::$_settings[$type] : array();

                    if (Arrays::exists('relationships', $settings)) {
                        if (Arrays::exists($key, $settings['relationships']) && 's' != $key[strlen($key) - 1]) {
                            return Data::getById($key, $this->$key);
                        }

                        if (Arrays::exists($key, $settings['relationships']) && 's' == $key[strlen($key) - 1]) {
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

            $this->values = isset($this->values) ? $this->values : array();
            $this->$key = $value;
            $this[$key] = $value;
            $this->values[$key] = $value;

            if (!Arrays::in($key, $this->_fields)) {
                $this->_fields[] = $key;
            }

            return $this;
        }

        public function offsetSet($key, $value)
        {
            $this->$key = $value;

            if (!Arrays::in($key, $this->_fields)) {
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
                    if (Arrays::is($k)) {
                        $this->populate($k, $namespace);
                    } else {
                        $this->$namespace = array_merge($this->$namespace, array($k => $v));
                    }
                }
            } else {
                foreach ($datas as $k => $v) {
                    if (Arrays::is($v)) {
                        $o = new self;
                        $o->populate($v);
                        $this->$k = $o;
                    } else {
                        $this->$k = $v;
                    }

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
                    Data::getModel($this->thin_type);
                    $type = $this->thin_type;
                    $settings = Arrays::exists($type, Data::$_settings) ? Data::$_settings[$type] : array();

                    if (Arrays::exists('relationships', $settings)) {
                        if (Arrays::exists($var, $settings['relationships'])) {
                            return Data::getById($var, $this->$var);
                        }
                    }
                }

                return $this->$var;
            } else {
                if (isset($this->thin_type)) {
                    Data::getModel($this->thin_type);
                    $type = $this->thin_type;
                    $settings = Arrays::exists($type, Data::$_settings) ? Data::$_settings[$type] : array();

                    if (Arrays::exists($var, $settings['relationships']) && 's' == $var[strlen($var) - 1]) {
                        if (Arrays::exists($var, $settings['relationships'])) {
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

            if (!Arrays::in($key, $this->_fields)) {
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

        public function assoc()
        {
            $collection = array();

            if (count($this->_fields)) {
                foreach ($this->_fields as $field) {
                    if ($field != 'values' && $field != '_nameClass') {
                        if (!$this->$field instanceof \Closure) {
                            if ($this->$field instanceof Object) {
                                $collection[$field] = $this->$field->assoc();
                            } else {
                                $collection[$field] = $this->$field;
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        public function toArray()
        {
            $collection = array();

            if (count($this->values)) {
                foreach ($this->values as $field => $value) {
                    $collection[$field] = $value;
                }
            }

            if (count($this->_fields)) {
                foreach ($this->_fields as $field) {
                    $collection[$field] = $this->$field;
                }
            }

            return $collection;
        }

        public function search(array $search)
        {
            if (!count($this->_fields) || !count($search)) {
                return false;
            }

            foreach($search as $key => $value) {
                if (!Arrays::in($key, $this->_fields)) {
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
                if (!Arrays::in($k, $this->_fields)) {
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
                Data::getModel($this->thin_type);
                $type = $this->thin_type;

                if (Arrays::exists($type, Data::$_fields)) {
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

        public function deleteLite($type)
        {
            $table          = $type . 's';
            $fields         = Arrays::exists($type, Data::$_fields) ? Data::$_fields[$type] : array();
            $settings       = Arrays::exists($type, Data::$_settings) ? Data::$_settings[$type] : array();

            if (count($fields) && count($settings)) {
                $dbType     = Arrays::exists('db', $settings) ? $settings['db'] : null;
                $checkId    = Arrays::exists('checkId', $settings) ? $settings['checkId'] : 'id';

                if (!empty($dbType)) {
                    $db     = Data::$dbType($type);
                    $delete = "DELETE FROM $table WHERE $checkId = '" . SQLite3::escapeString($this->$checkId) . "'";
                    $db->exec($delete);
                }
            }
        }

        public function saveLite($type)
        {
            $table          = $type . 's';
            $fields         = Arrays::exists($type, Data::$_fields) ? Data::$_fields[$type] : array();
            $settings       = Arrays::exists($type, Data::$_settings) ? Data::$_settings[$type] : array();

            if (count($fields) && count($settings)) {
                $dbType     = Arrays::exists('db', $settings) ? $settings['db'] : null;
                $checkId    = Arrays::exists('checkId', $settings) ? $settings['checkId'] : 'id';

                if (!empty($dbType)) {
                    $data   = $this->toArray();
                    $fields['id'] = array();
                    $fields['date_create'] = array();

                    $id = Arrays::exists('id', $data) ? $data['id'] : Data::getKeyLite($type);
                    $date_create = Arrays::exists('date_create', $data) ? $data['date_create'] : time();

                    $this->id = $data['id'] = $id;
                    $this->date_create = $data['date_create'] = $date_create;

                    $fields = array_keys($fields);
                    $db     = Data::$dbType($type);
                    $q      = "SELECT $checkId FROM $table WHERE $checkId = '" . $data[$checkId] . "'";
                    $res    = $db->query($q);

                    if(false === $res->fetchArray()) {
                        $values = array();
                        foreach ($fields as $field) {
                            $values[] = "'" . SQLite3::escapeString($data[$field]) . "'";
                        }
                        $values = implode(', ', $values);
                        $insert = "INSERT INTO $table
                        (" . implode(', ', $fields) . ")
                        VALUES ($values)";
                        $db->exec($insert);
                    } else {
                        $update = "UPDATE $table SET ";

                        foreach ($fields as $field) {
                            if ($field != $checkId) {
                                $update .= "$field = '" . SQLite3::escapeString($data[$field]) . "', ";
                            }
                        }

                        $update = substr($update, 0, -2);
                        $update .= " WHERE $checkId = '" . $data[$checkId] . "'";
                        $db->exec($update);
                    }
                }
            }

            return $this;
        }

        public function func($id, \Closure $c)
        {
            event($id, $c);

            return $this;
        }
    }
