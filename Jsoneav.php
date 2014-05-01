<?php
    namespace Thin;

    class Jsoneav
    {
        private $entity;
        private $dbEntity;
        private $dbAttribute;
        private $dbValue;
        private $dbItem;
        private $lastInsertId;
        private $results;
        private $wheres         = array();
        private $cache          = true;
        private $ttl            = 3600;
        private static $configs = array();

        public function __construct($entity)
        {
            $this->entity       = $entity;
            $this->dbEntity     = new Jsondb("thin_eav_entity");
            $this->dbAttribute  = new Jsondb("thin_eav_attribute");
            $this->dbValue      = new Jsondb("thin_eav_value");
            $this->dbItem       = new Jsondb("eav_" . $entity);

            $settings = isAke(self::$configs, $entity);
            $event = isAke($settings, 'cache');
            if (!empty($event)) {
                Jsondb::configs('thin_eav_entity', 'cache', $event);
                Jsondb::configs('thin_eav_attribute', 'cache', $event);
                Jsondb::configs('thin_eav_value', 'cache', $event);
                Jsondb::configs("eav_" . $entity, 'cache', $event);
            }
        }

        public function setCache($bool = true)
        {
            $this->dbEntity->setCache($bool);
            $this->dbAttribute->setCache($bool);
            $this->dbValue->setCache($bool);
            $this->dbItem->setCache($bool);
            $this->cache = $bool;
            return $this;
        }

        public function save($record)
        {
            if (is_object($record)) {
                $record = $record->assoc();
            }
            $id = isAke($record, 'id', null);
            if (strlen($id)) {
                return $this->edit($id, $record);
            } else {
                return $this->add($record);
            }
        }

        private function exists($name)
        {
            $keyCache = sha1('existsJS_' . serialize($name) . $this->entity);
            $cached = $this->cached($keyCache);
            if (empty($cached)) {
                $res = $this->dbEntity->findOneByName($name);
                $val = empty($res) ? false : $res->getId();
                $this->cached($keyCache, $val);
                return $val;
            }
            return $cached;
        }

        private function add($record)
        {
            $this->event('beforeAdd', $record);
            $name = $this->entity . '_' . sha1(serialize($record));
            $exists = $this->exists($name);
            if (false === $exists) {
                $entity = array(
                    'name'          => $name,
                    'date_create'   => time(),
                    'date_modify'   => time()
                );
                $this->dbEntity->push($entity);
                $this->lastInsertId = $entityId = $this->dbEntity->getLastId();

                foreach ($record as $k => $v) {
                    $attributeId    = $this->attribute($k);
                    $valueId        = $this->value($v);
                    $item = array(
                        'e'         => $entityId,
                        1           => $attributeId,
                        2           => $valueId,
                    );
                    $this->dbItem->push($item);
                }
                $this->all(true);
            } else {
                $this->lastInsertId = $exists;
            }
            $this->event('afterAdd', $this->lastInsertId);
            return $this;
        }

        private function edit($id, $record)
        {
            $this->event('beforeEdit', array($id, $record));
            $rows = $this->dbItem->findObjectsByE($id);
            while (!empty($rows)) {
                $row = Arrays::first($rows);
                $row->delete();
                $rows = $this->dbItem->findObjectsByE($id);
            }
            unset($record['id']);

            foreach ($record as $k => $v) {
                $attributeId    = $this->attribute($k);
                $valueId        = $this->value($v);
                $item = array(
                    'e'         => $id,
                    1           => $attributeId,
                    2           => $valueId,
                );
                $this->dbItem->push($item);
            }

            $this->dbEntity->find($id)->setDateModify(time())->record();
            $this->all(true);

            $this->event('afterEdit', array($id, $record));

            return $this;
        }

        public function delete($id)
        {
            $this->event('beforeDelete', $id);
            $rows = $this->dbItem->findObjectsByE($id);
            $entity = $this->dbEntity->find($id);
            while (!empty($rows)) {
                $row = Arrays::first($rows);
                $row->delete();
                $rows = $this->dbItem->findObjectsByE($id);
            }
            $entity->delete();
            $this->all(true);

            $this->event('afterDelete', $id);

            return $this;
        }

        private function attribute($a)
        {
            $keyCache = sha1('attributeJS_' . serialize($a) . $this->entity);
            $cached = $this->cached($keyCache);
            if (empty($cached)) {
                $res = $this->dbAttribute->findOneByName($a);
                if (!empty($res)) {
                    return $res->getId();
                }
                $attribute = array(
                    'name' => $a
                );
                $this->dbAttribute->push($attribute);
                $val = $this->dbAttribute->getLastId();
                $this->cached($keyCache, $val);
                return $val;
            }
            return $cached;
        }

        private function value($v)
        {
            $keyCache = sha1('valueJS_' . serialize($v) . $this->entity);
            $cached = $this->cached($keyCache);
            if (empty($cached)) {
                $res = $this->dbValue->findOneByName($v);
                if (!empty($res)) {
                    return $res->getId();
                }
                $value = array(
                    'name' => $v
                );
                $this->dbValue->push($value);
                $val = $this->dbValue->getLastId();
                $this->cached($keyCache, $val);
                return $val;
            }
            return $cached;
        }

        public function getLastId()
        {
            return $this->lastInsertId;
        }

        public function exec($object = false)
        {
            if (false === $object) {
                return $this->results;
            }
            $collection = array();
            foreach ($this->results as $res) {
                $tmp = $this->toObject($res);
                array_push($collection, $tmp);
            }
            $this->reset();
            return $collection;
        }

        public function find($id, $object = true)
        {
            $items = $this->dbItem->findByE($id);
            if (!empty($items)) {
                return $this->row($items, $object);
            }
        }

        public function findBy($field, $value, $one = false, $object = false)
        {
            $res = $this->search("$field = $value");
            if (count($res) && true === $one) {
                return $this->row(Arrays::first($res));
            }
            return $this->exec($object);
        }

        private function row($rows, $object = true)
        {
            $tab = $this->makeTab($rows);
            if (true === $object) {
                return $this->toObject($tab);
            } else {
                return $tab;
            }
        }

        public function create($tab = array())
        {
            return $this->toObject($tab);
        }

        public function toObject($tab = array())
        {
            $o = new Container;
            $o->populate($tab);
            return $this->functions($o);
        }

        private function functions($obj)
        {
            $settings = isAke(self::$configs, $this->entity);
            $class = $this;
            $class->results = null;
            $class->wheres  = null;
            // $class->configs = null;

            $save = function () use ($class, $obj) {
                return $class->save($obj->assoc());
            };

            $delete = function () use ($class, $obj) {
                return $class->delete($obj->getId());
            };

            $date = function ($f) use ($obj) {
                return date('Y-m-d H:i:s', $obj->$f);
            };

            $hydrate = function ($data) use ($obj) {
                if (Arrays::isAssoc($data)) {
                    foreach ($data as $k => $v) {
                        $obj->$k = $v;
                    }
                }
                return $obj;
            };

            $display = function ($field, $echo = true)  use ($obj) {
                $val = Html\Helper::display($obj->$field);
                if (true === $echo) {
                    echo $val;
                } else {
                    return $val;
                }
            };

            $tab = function () use ($obj) {
                return $obj->assoc();
            };

            $asset = function ($field) use ($obj) {
                return '/storage/img/' . $obj->$field;
            };

            $obj->event('save', $save)
            ->event('delete', $delete)
            ->event('date', $date)
            ->event('hydrate', $hydrate)
            ->event('tab', $tab)
            ->event('asset', $asset)
            ->event('display', $display);

            $functions = isAke($settings, 'functions');

            if (count($functions)) {
                foreach ($functions as $closureName => $callable) {
                    if (version_compare(PHP_VERSION, '5.4.0', "<")) {
                        $share = function () use ($obj, $callable) {
                            return $callable($obj);
                        };
                    } else {
                        $share = $callable->bindTo($obj);
                    }
                    $obj->event($closureName, $share);
                }
            }

            return $obj;
        }

        private function makeTab($rows)
        {
            if (!empty($rows)) {
                $keyCache = sha1('tabDB_' . serialize($rows) . $this->entity);
                $cached = $this->cached($keyCache);
                if (empty($cached)) {
                    $tab = array();
                    $first = true;
                    foreach ($rows as $row) {
                        if (true === $first) {
                            $tab['id'] = $row['e'];
                        }
                        $attribute  = $this->dbAttribute->find($row[1])->getName();
                        $value      = $this->dbValue->find($row[2])->getName();
                        $tab[$attribute] = $value;
                        $first = false;
                    }
                    $this->cached($keyCache, $tab);
                    return $tab;
                }
                return $cached;
            }
        }

        private function all($force = false)
        {
            $collection = false === $force
            ? $this->cached('eavJS_all_db_' . $this->entity)
            : array();
            if (empty($collection)) {
                $collection = array();
                $entities = $this->dbEntity->fetch()->exec();
                foreach ($entities as $entity) {
                    $items = $this->dbItem->findByE($entity['id']);
                    array_push($collection, $this->makeTab($items));
                }
                $this->cached('eavJS_all_db_' . $this->entity, $collection);
            }
            return $collection;
        }

        public function fetch()
        {
            $this->results = $this->all();
            return $this;
        }

        public function reset()
        {
            $this->results = null;
            $this->wheres  = array();
            return $this;
        }

        public function groupBy($field, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $keyCache = sha1('eavJS_groupby_' . $field . serialize($res) . $this->entity);

            $groupBys = $this->cached($keyCache);
            if (empty($groupBys)) {
                $groupBys   = array();
                $ever       = array();
                foreach ($res as $id => $tab) {
                    $obj = isAke($tab, $field, null);
                    if (!Arrays::in($obj, $ever)) {
                        $groupBys[$id]  = $tab;
                        $ever[]         = $obj;
                    }
                }
                $this->cached($keyCache, $groupBys);
            }
            $this->results = $groupBys;
            $this->order($field);

            return $this;
        }

        public function limit($limit, $offset = 0, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $this->results = array_slice($res, $offset, $limit);
            return $this;
        }

        public function sum($field, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $sum = 0;

            if (count($res)) {
                foreach ($res as $id => $tab) {
                    $val = isAke($tab, $field, 0);
                    $sum += $val;
                }
            }
            $this->reset();
            return $sum;
        }

        public function avg($field, $results = array())
        {
            return ($this->sum($field, $results) / count($res));
        }

        public function min($field, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $min = 0;
            if (count($res)) {
                $first = true;
                foreach ($res as $id => $tab) {
                    $val = isAke($tab, $field, 0);
                    if (true === $first) {
                        $min = $val;
                    } else {
                        $min = $val < $min ? $val : $min;
                    }
                    $first = false;
                }
            }
            $this->reset();
            return $min;
        }

        public function max($field, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $max = 0;
            if (count($res)) {
                $first = true;
                foreach ($res as $id => $tab) {
                    $val = isAke($tab, $field, 0);
                    if (true === $first) {
                        $max = $val;
                    } else {
                        $max = $val > $max ? $val : $max;
                    }
                    $first = false;
                }
            }
            $this->reset();
            return $max;
        }

        public function order($fieldOrder, $orderDirection = 'ASC', $results = array())
        {
            $res = count($results) ? $results : $this->results;
            if (empty($res)) {
                return $this;
            }

            $keyCache = sha1(
                'eavJS_order_' .
                serialize($fieldOrder) .
                serialize($orderDirection) .
                serialize($res) .
                $this->entity
            );
            $cached = $this->cached($keyCache);

            if (empty($cached)) {
                $settings   = isAke(self::$configs, $this->entity);
                $_fields    = isAke($settings, 'fields');
                $fields     = empty($_fields) ? array_keys(Arrays::first($res)) : $_fields;

                $sort = array();
                foreach($res as $i => $tab) {
                    foreach ($fields as $k) {
                        $value = isAke($tab, $k, null);
                        $sort[$k][] = $value;
                    }
                }

                $asort = array();
                foreach ($sort as $key => $rows) {
                    for ($i = 0 ; $i < count($rows) ; $i++) {
                        if (empty($$key) || is_string($$key)) {
                            $$key = array();
                        }
                        $asort[$i][$key] = $rows[$i];
                        array_push($$key, $rows[$i]);
                    }
                }

                if (Arrays::is($fieldOrder) && !Arrays::is($orderDirection)) {
                    $t = array();
                    foreach ($fieldOrder as $tmpField) {
                        array_push($t, $orderDirection);
                    }
                    $orderDirection = $t;
                }

                if (Arrays::is($fieldOrder) && Arrays::is($orderDirection)) {
                    if (count($orderDirection) < count($fieldOrder)) {
                        throw new Exception('You must provide the same arguments number of fields sorting and directions sorting.');
                    }
                    if (count($fieldOrder) == 1) {
                        $fieldOrder = Arrays::first($fieldOrder);
                        if ('ASC' == Inflector::upper(Arrays::first($orderDirection))) {
                            array_multisort($$fieldOrder, SORT_ASC, $asort);
                        } else {
                            array_multisort($$fieldOrder, SORT_DESC, $asort);
                        }
                    } elseif(count($fieldOrder) > 1) {
                        $params = array();
                        foreach ($fieldOrder as $k => $tmpField) {
                            $tmpSort    = isset($orderDirection[$k]) ? $orderDirection[$k] : 'ASC';
                            $params[]   = $$tmpField;
                            $params[]   = 'ASC' == $tmpSort ? SORT_ASC : SORT_DESC;
                        }
                        $params[] = $asort;
                        call_user_func_array('array_multisort', $params);
                    }
                } else {
                    if ('ASC' == Inflector::upper($orderDirection)) {
                        array_multisort($$fieldOrder, SORT_ASC, $asort);
                    } else {
                        array_multisort($$fieldOrder, SORT_DESC, $asort);
                    }
                }
                $collection = array();
                foreach ($asort as $key => $row) {
                    array_push($collection, $row);
                }
                $this->cached($keyCache, $collection);
            } else {
                $collection = $cached;
            }

            $this->results = $collection;
            return $this;
        }

        public function _and($condition, $results = array())
        {
            return $this->where($condition, 'AND', $results);
        }

        public function _or($condition, $results = array())
        {
            return $this->where($condition, 'OR', $results);
        }

        public function _xor($condition, $results = array())
        {
            return $this->where($condition, 'XOR', $results);
        }

        public function whereAnd($condition, $results = array())
        {
            return $this->where($condition, 'AND', $results);
        }

        public function whereOr($condition, $results = array())
        {
            return $this->where($condition, 'OR', $results);
        }

        public function whereXor($condition, $results = array())
        {
            return $this->where($condition, 'XOR', $results);
        }

        public function where($condition, $op = 'AND', $results = array())
        {
            $res = $this->search($condition, $results);
            if (!count($this->wheres)) {
                $this->results = array_values($res);
            } else {
                switch ($op) {
                    case 'AND':
                        $this->results = array_intersect($this->results, array_values($res));
                        break;
                    case 'OR':
                        $this->results = array_merge($this->results, array_values($res));
                        break;
                    case 'XOR':
                        $this->results = array_merge(
                            array_diff(
                                $this->results,
                                array_values($res),
                                array_diff(
                                    array_values($res),
                                    $this->results
                                )
                            )
                        );
                        break;
                }
            }
            $this->wheres[] = $condition;
            return $this;
        }

        private function search($condition, $results = array(), $populate = true)
        {
            $collection = array();
            $datas = !count($results) ? $this->all() : $results;

            $keyCache = sha1('eavJS_search_' . $condition . serialize($datas) . $this->entity);

            $cached = $this->cached($keyCache);
            if (empty($cached)) {
                if(count($datas)) {
                    $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                    $condition  = repl('NOT IN', 'NOTIN', $condition);

                    list($field, $op, $value) = explode(' ', $condition, 3);

                    foreach ($datas as $id => $tab) {
                        if (!empty($tab)) {
                            if ($field == 'id') {
                                $val = $id;
                            } else {
                                $val = isAke($tab, $field, null);
                            }
                            if (strlen($val)) {
                                $val = repl('|', ' ', $val);
                                $check = $this->compare($val, $op, $value);
                            } else {
                                $check = ('null' == $value) ? true : false;
                            }
                            if (true === $check) {
                                array_push($collection, $tab);
                            }
                        }
                    }
                    $this->cached($keyCache, $collection);
                }
            } else {
                $collection = $cached;
            }

            if (true === $populate) {
                $this->results = $collection;
            }
            return $collection;
        }

        private function compare($comp, $op, $value)
        {
            $keyCache = sha1('compare_' . serialize(func_get_args()));
            $cached = $this->cached($keyCache);
            if (empty($cached)) {
                $res = false;
                if (isset($comp)) {
                    $comp   = Inflector::lower($comp);
                    $value  = Inflector::lower($value);
                    switch ($op) {
                        case '=':
                            $res = sha1($comp) == sha1($value);
                            break;
                        case '>=':
                            $res = $comp >= $value;
                            break;
                        case '>':
                            $res = $comp > $value;
                            break;
                        case '<':
                            $res = $comp < $value;
                            break;
                        case '<=':
                            $res = $comp <= $value;
                            break;
                        case '<>':
                        case '!=':
                            $res = sha1($comp) != sha1($value);
                            break;
                        case 'LIKE':
                            $value = repl("'", '', $value);
                            $value = repl('%', '', $value);
                            if (strstr($comp, $value)) {
                                $res = true;
                            }
                            break;
                        case 'NOTLIKE':
                            $value = repl("'", '', $value);
                            $value = repl('%', '', $value);
                            if (!strstr($comp, $value)) {
                                $res = true;
                            }
                            break;
                        case 'LIKE START':
                            $value = repl("'", '', $value);
                            $value = repl('%', '', $value);
                            $res = (substr($comp, 0, strlen($value)) === $value);
                            break;
                        case 'LIKE END':
                            $value = repl("'", '', $value);
                            $value = repl('%', '', $value);
                            if (!strlen($comp)) {
                                $res = true;
                            }
                            $res = (substr($comp, -strlen($value)) === $value);
                            break;
                        case 'IN':
                            $value = repl('(', '', $value);
                            $value = repl(')', '', $value);
                            $tabValues = explode(',', $value);
                            $res = Arrays::in($comp, $tabValues);
                            break;
                        case 'NOTIN':
                            $value = repl('(', '', $value);
                            $value = repl(')', '', $value);
                            $tabValues = explode(',', $value);
                            $res = !Arrays::in($comp, $tabValues);
                            break;
                    }
                }
                $this->cached($keyCache, $res);
                return $res;
            }
            return $cached;
        }

        private function cached($key, $value = null)
        {
            if (false === $this->cache) {
                return null;
            }
            $settings = isAke(self::$configs, $this->entity);
            $event = isAke($settings, 'cache');
            if (!empty($event)) {
                return $this->$event($key, $value);
            } else die('oco js');
            $file = STORAGE_PATH . DS . 'cache' . DS . $key . '.eav';
            if (empty($value)) {
                if (File::exists($file)) {
                    $age = filemtime($file);
                    $maxAge = time() - $this->ttl;
                    if ($maxAge < $age) {
                        return json_decode(File::get($file), true);
                    } else {
                        File::delete($file);
                        return null;
                    }
                }
            } else {
                if (File::exists($file)) {
                    File::delete($file);
                }
                File::put($file, json_encode($value));
                return true;
            }
        }

        private function redis($key, $value = null)
        {
            $db = container()->redis();
            if (empty($value)) {
                $val = $db->get($key);
                if (strlen($val)) {
                    return json_decode($val, true);
                }
                return null;
            } else {
                $db->set($key, json_encode($value));
                $db->expire($key, $this->ttl);
                return true;
            }
        }

        public function setTtl($ttl = 3600)
        {
            $this->ttl = $ttl;
            return $this;
        }

        public static function configs($entity, $key, $value = null)
        {
            if (!strlen($entity)) {
                throw new Exception("An entity must be provided to use this method.");
            }
            if (!Arrays::exists($entity, static::$configs)) {
                self::$configs[$entity] = array();
            }
            if (empty($value)) {
                if (!strlen($key)) {
                    throw new Exception("A key must be provided to use this method.");
                }
                return isAke(self::$configs[$entity], $key, null);
            }

            if (!strlen($key)) {
                throw new Exception("A key must be provided to use this method.");
            }
            self::$configs[$entity][$key] = $value;
        }

        private function event($id, $args = array())
        {
            $settings = isAke(self::$configs, $this->entity);
            $event = isAke($settings, $id);
            if (!empty($event)) {
                if (is_callable($event)) {
                    if (version_compare(PHP_VERSION, '5.4.0', ">=")) {
                        $event = $event->bindTo($this);
                    }
                    return call_user_func_array($event , $args);
                }
            }
        }

        public function __call($method, $parameters)
        {
            if (substr($method, 0, 6) == 'findBy') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 6)));
                $field = Inflector::lower($uncamelizeMethod);
                $value = Arrays::first($parameters);
                return $this->findBy($field, $value);
            } elseif (substr($method, 0, strlen('findObjectsBy')) == 'findObjectsBy') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, strlen('findObjectsBy'))));
                $field = Inflector::lower($uncamelizeMethod);
                $value = Arrays::first($parameters);
                return $this->findBy($field, $value, false, true);
            } elseif (substr($method, 0, 9) == 'findOneBy') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 9)));
                $field = Inflector::lower($uncamelizeMethod);
                $value = Arrays::first($parameters);
                return $this->findBy($field, $value, true);
            } else {
                $settings   = isAke(self::$configs, $this->entity);
                $event      = isAke($settings, $method);
                if (!empty($event)) {
                    if (is_callable($event)) {
                        if (version_compare(PHP_VERSION, '5.4.0', ">=")) {
                            $event = $event->bindTo($this);
                        }
                        return call_user_func_array($event , $parameters);
                    }
                } else {
                    throw new Exception("The method '$method' is not callable.");
                }
            }
        }

        public function __toString()
        {
            return $this->entity;
        }
    }
