<?php
    namespace Thin;

    class Memorydb
    {
        private $entity;
        private $db;
        private $lastInsertId;
        private $lock;
        private $results;
        private $begin;
        private $transactions   = array();
        private $wheres         = array();
        private $cache          = false;
        private $debug          = false;
        private $ttl            = 3600;
        private static $configs = array();
        private static $queries = 0;

        public function __construct($entity)
        {
            $this->entity   = $entity;
            $this->db       = 'db_' . $entity;
            $this->lock     = Inflector::camelize('redis_db');
        }

        public function begin()
        {
            $key = 'begin::db_' . $this->entity;
            container()->redis()->set($key, json_encode($this->all(true)));
            $this->begin = $key;
            return $this;
        }

        public function rollback()
        {
            $data = container()->redis()->get($this->begin);
            if (strlen($data)) {
                $begin = json_decode($data, true);
                foreach ($begin as $row) {
                    $this->save($row);
                }
            }
            $this->reset();
            container()->redis()->del($this->begin);
            return $this;
        }

        public function commit()
        {
            if (count($this->transactions)) {
                if (null === $this->begin) {
                    $this->begin();
                }
                foreach ($this->transactions as $transaction) {
                    list($method, $params) = $transaction;
                    $commit = call_user_func_array(array($this, $method), $params);
                }
                $this->transactions = array();
                $this->reset();
            }
        }

        public function transaction($method)
        {
            $params = array_slice(func_get_args(), 1);
            array_push($this->transactions, array($method, $params));
        }

        public function countAll()
        {
            return count($this->all(true));
        }

        public function count()
        {
            return count($this->results);
        }

        public function post()
        {
            return $this->save($_POST);
        }

        public function save($data)
        {
            if (is_object($data)) {
                $data = $data->assoc();
            }

            $id = isAke($data, 'id', null);
            if (strlen($id)) {
                return $this->edit($id, $data);
            } else {
                return $this->add($data);
            }
        }

        private function add($data)
        {
            $this->lock('add');
            $data = $this->checkValues($data);
            $key = sha1(serialize($data) . $this->entity);
            $exists = container()->redis()->get($key);
            if (!strlen($exists)) {
                if (!Arrays::is($data)) {
                    return $data;
                }
                $this->lastInsertId = $id = container()->redis()->incr($this->entity . '_count');
                $data['id'] = $id;
                $add = container()->redis()->set($this->db . '::' . $id, json_encode($data));
                $this->all(true);
                container()->redis()->set($key, $id);
                $this->unlock('add');
                return $this->find($id);
            }
            $this->unlock('add');
            $this->lastInsertId = $exists;
            return $this->find($exists);
        }

        private function edit($id, $data)
        {
            $this->delete($id);
            $this->lock('edit');
            $clone = $data;
            unset($clone['id']);
            $key = sha1(serialize($clone) . $this->entity);
            $exists = container()->redis()->get($key);
            if (!strlen($exists)) {
                $data = $this->checkValues($data);
                if (!Arrays::is($data)) {
                    return $data;
                }
                $edit = container()->redis()->set($this->db . '::' . $id, json_encode($data));
                $this->all(true);
                container()->redis()->set($key, $id);
                $this->unlock('edit');
                return $this->find($id);
            }
            return $this->find($exists);
        }

        public function delete($id)
        {
            $row = $this->find($id, false);
            unset($row['id']);
            $key = sha1(serialize($row) . $this->entity);
            $this->lock('delete');
            container()->redis()->del($this->db . '::' . $id);
            container()->redis()->del($key);
            $this->unlock('delete');
            return $this;
        }

        public function update($update, $where = null)
        {
            $res = !empty($where) ? $this->where($where)->exec() : $this->all(true);
            if (count($res)) {
                list($field, $newValue) = explode(' = ', $update, 2);
                foreach ($res as $row) {
                    $val = isAke($row, $field, null);
                    if ($val != $newValue) {
                        $row[$field] = $newValue;
                        $this->edit($row['id'], $row);
                    }
                }
            }
            return $this;
        }

        public function drop()
        {
            return $this->remove();
        }

        public function flushAll()
        {
            return $this->remove();
        }

        public function remove($where = null)
        {
             $res = !empty($where) ? $this->where($where)->exec() : $this->all(true);
            if (count($res)) {
                foreach ($res as $row) {
                    $this->delete($row['id']);
                }
            }
            return $this;
        }

        private function indexedValues()
        {
            $rows = container()->redis()->keys($this->db . '::index::*');
            $collection = array();
            foreach ($rows as $k => $row) {
                $tab = json_decode(container()->redis()->get($row), true);
                array_push($collection, $tab);
            }
            return $collection;
        }

        private function addIndexedValue($id, $value)
        {
            $row = container()->redis()->get($this->db . '::index::' . sha1($value));
            if (strlen($row)) {
                $tab = json_decode(container()->redis()->get($row), true);
            } else {
                $tab = array();
            }
            if (!Arrays::in($id, $tab) {
                $tab[] = $id;
            }
            container()->redis()->set($this->db . '::index::' . sha1($value), json_encode($tab));
        }

        private function indexedValue($value)
        {
            $row = container()->redis()->get($this->db . '::index::' . sha1($value));
            if (strlen($row)) {
                return json_decode(container()->redis()->get($row), true);
            }
            return array();
        }

        private function indexes()
        {
            return isAke(isAke(self::$configs, $this->entity), 'indexs');
        }

        private function checkValues($data)
        {
            $settings   = isAke(self::$configs, $this->entity);
            $defaults   = isAke($settings, 'defaults');
            $requires   = isAke($settings, 'requires');
            $controls   = isAke($settings, 'controls');
            $uniques    = isAke($settings, 'uniques');

            if (count($defaults)) {
                foreach ($defaults as $default => $value) {
                    $val = isAke($data, $default, null);
                    if (empty($val)) {
                        $data[$default] = $value;
                    }
                }
            }

            if (count($controls)) {
                foreach ($controls as $control => $callable) {
                    $val = isAke($data, $control, null);
                    if (is_callable($callable)) {
                        $data[$control] = $callable($val);
                    }
                }
            }

            if (count($requires)) {
                foreach ($requires as $require) {
                    $val = isAke($data, $require, null);
                    if (empty($val)) {
                        return "The field $require is required.";
                    }
                }
            }

            if (count($uniques)) {
                foreach ($uniques as $unique) {
                    $val = isAke($data, $unique, null);
                    if (!empty($val)) {
                        $exists = $this->where("$unique = $val")->first();
                        if (count($exists)) {
                            $id = isAke($data, 'id', null);
                            if (!empty($id)) {
                                $idExists = isAke($exists, 'id', null);
                                if ($idExists != $id) {
                                    return "The field $unique must be unique.";
                                }
                            } else {
                                return "The field $unique must be unique.";
                            }
                        }
                    }
                }
            }
            return $data;
        }

        public function find($id, $object = true)
        {
            return $this->findBy('id', $id, true, $object);
        }

        public function findBy($field, $value, $one = false, $object = false)
        {
            $res = $this->search("$field = $value");
            if (count($res) && true === $one) {
                return $object ? $this->toObject(Arrays::first($res)) : Arrays::first($res);
            }
            if (!count($res) && true === $one && true === $object) {
                return null;
            }
            return $this->exec($object);
        }

        public function first($object = false)
        {
            if (true === $object) {
                return count($this->results) ? $this->toObject(Arrays::first($this->results)) : null;
            } else {
                return count($this->results) ? Arrays::first($this->results) : array();
            }
        }

        public function last($object = false)
        {
            if (true === $object) {
                return count($this->results) ? $this->toObject(Arrays::last($this->results)) : null;
            } else {
                return count($this->results) ? Arrays::last($this->results) : array();
            }
        }

        public function exec($object = false)
        {
            $collection = array();
            if (count($this->results)) {
                foreach ($this->results as $row) {
                    $item = $object ? $this->toObject($row) : $row;
                    array_push($collection, $item);
                }
            }
            if (!count($collection) && true === $object) {
                return null;
            }
            return $collection;
        }

        public function all($force = false)
        {
            $cached = false === $force
            ? $this->cached('RDB_allDb_' . $this->entity)
            : array();
            if (empty($cached)) {
                $rows = container()->redis()->keys($this->db . '::*');
                $collection = array();
                foreach ($rows as $k => $row) {
                    $tab = json_decode(container()->redis()->get($row), true);
                    array_push($collection, $tab);
                }
                $this->cached('RDB_allDb_' . $this->entity, $collection);
                return $collection;
            } else {
                return $cached;
            }
        }

        public function fetch()
        {
            $this->results = $this->all();
            return $this;
        }

        public function reset()
        {
            $this->results = null;
            $this->begin = null;
            $this->wheres  = array();
            $this->transactions  = array();
            return $this;
        }

        public function groupBy($field, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $keyCache = sha1('RDB_groupby_' . $field . serialize($res) . $this->entity);

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
            $res            = count($results) ? $results : $this->results;
            $offset         = count($res) < $offset ? count($res) : $offset;
            $this->results  = array_slice($res, $offset, $limit);
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
                'RDB_order_' .
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
            $res = $this->search($condition, $results, false);
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

        private function search($condition = null, $results = array(), $populate = true)
        {
            self::$queries++;
            $collection = array();
            $datas      = !count($results) ? $this->all() : $results;
            if (empty($condition)) {
                return $datas;
            }

            $keyCache   = sha1('eavRDB_search_' . $condition . serialize($datas) . $this->entity);

            $cached     = $this->cached($keyCache);
            if (empty($cached)) {
                if(count($datas)) {
                    $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                    $condition  = repl('NOT IN', 'NOTIN', $condition);

                    list($field, $op, $value) = explode(' ', $condition, 3);

                    foreach ($datas as $tab) {
                        if (!empty($tab)) {
                            $val = isAke($tab, $field, null);
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

        public function create($tab = array())
        {
            return $this->toObject($tab);
        }

        public function toObject($tab = array())
        {
            $o = new Container;
            $o->populate($tab);
            return $this->closures($o);
        }

        private function closures($obj)
        {
            $settings = isAke(self::$configs, $this->entity);
            $class = $this;
            $class->results = null;
            $class->wheres  = null;
            // $class->configs = null;

            $export = function () use ($class, $obj) {
                if (isset($obj->id)) {
                    $class->where("id = " . $obj->id)->export();
                }
            };

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

            $extend = function ($n, \Closure $f) use ($obj) {
            if (version_compare(PHP_VERSION, '5.4.0', "<")) {
                    $share = function () use ($obj, $f) {
                        return $f($obj);
                    };
                } else {
                    $share = $f->bindTo($obj);
                }
                $obj->event($n, $share);
            };

            $obj->event('save', $save)
            ->event('delete', $delete)
            ->event('extend', $extend)
            ->event('date', $date)
            ->event('hydrate', $hydrate)
            ->event('tab', $tab)
            ->event('export', $export)
            ->event('display', $display);

            $functions = isAke($settings, 'functions');

            if (count($functions)) {
                foreach ($functions as $closureName => $callable) {
                    $closureName = lcfirst(Inflector::camelize($closureName));
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

        public function getLastId()
        {
            return $this->lastInsertId;
        }

        public function setCache($bool = true)
        {
            $this->cache = $bool;
            return $this;
        }

        public function setDebug($bool = true)
        {
            $this->debug = $bool;
            return $this;
        }

        private function cached($key, $value = null)
        {
            if (false === $this->cache) {
                return null;
            }
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

        public function config($key, $value = null)
        {
            self::configs($this->entity, $key, $value);
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
            $reverse = strrev($key);
            $last = $reverse{0};
            if ('s' == $last) {
                self::$configs[$entity][$key] = $value;
            } else {
                if (!Arrays::is(self::$configs[$entity][$key . 's'])) {
                    self::$configs[$entity][$key . 's'] = array();
                }
                self::$configs[$entity][$key . 's'] += $value;
            }
            return true;
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
                    if (count($parameters) == 1) {
                        return self::configs($this->entity, $method, Arrays::first($parameters));
                    } else {
                        throw new Exception("The method '$method' is not callable in this class.");
                    }
                }
            }
        }

        public function __toString()
        {
            return $this->entity;
        }

        private function lock($action)
        {
            if (true === $this->debug) {
                container()->log("lock " . $action);
            }
            $this->waitUnlock($action);
            container()->redis()->set($this->lock, time());
            return $this;
        }

        private function unlock($action)
        {
            if (true === $this->debug) {
                container()->log("unlock " . $action);
            }
            container()->redis()->del($this->lock);
            return $this;
        }

        private function waitUnlock($action)
        {
            if (true === $this->debug) {
                container()->log("wait " . $action);
            }
            $wait = strlen(container()->redis()->get($this->lock)) ? true : false;
            $i = 1;
            while (true == $wait) {
                if (1000 == $i) {
                    $this->unlock('forced ' . $action);
                }
                usleep(100);
                $wait = strlen(container()->redis()->get($this->lock)) ? true : false;
                $i++;
            }
            return $this;
        }

        public function import($csv)
        {
            $datas      = explode("\n", $csv);
            $fields     = explode(';', Arrays::first($datas));
            $count      = count($datas);

            for ($i = 1; $i < $count; $i++) {
                $data   = trim($datas[$i]);
                $row    = array();
                $j      = 0;
                $values = explode(';', $data);
                foreach ($fields as $field) {
                    $row[$field] = $values[$j];
                    $j++;
                }
                $this->save($row);
            }
        }

        public function export($q = null, $type = 'csv')
        {
            if (!empty($this->wheres)) {
                $datas = $this->results;
            } else {
                if (!empty($q)) {
                    $this->wheres[] = $q;
                    $datas = $this->search($q);
                } else {
                    $datas = $this->all(true);
                }
            }
            if (count($datas)) {
                $settings   = isAke(self::$configs, $this->entity);
                $_fields    = isAke($settings, 'fields');
                $fields     = empty($_fields) ? array_keys(Arrays::first($datas)) : $_fields;
                $rows = array();
                $rows[] = implode(';', $fields);
                foreach ($datas as $row) {
                    $tmp = array();
                    foreach ($fields as $field) {
                        $value = isAke($row, $field, null);
                        $tmp[] = $value;
                    }
                    $rows[] = implode(';', $tmp);
                }
                $this->$type($rows);
            } else {
                if (count($this->wheres)) {
                    $this->reset();
                    die('This query has no result.');
                } else {
                    die('This database is empty.');
                }
            }
        }

        private function csv($data)
        {
            $csv = implode("\n", $data);
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: private", false);
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"Export.csv\";" );
            header("Content-Transfer-Encoding: binary");
            die($csv);
        }

        public function sql($sql)
        {
            $infos = $this->parseQuery($sql);
            $method = isAke($infos, 'method', null);
            if (!empty($method)) {
                $sqlMethod = lcfirst(Inflector::camelize('sql_' . $method));
                if (method_exists($this, $sqlMethod)) {
                    return $this->$sqlMethod($infos);
                }
            }
            return $this;
        }

        private function sqlSelect($infos)
        {
            $and    = isAke($infos['wheres'], 'AND');
            $or     = isAke($infos['wheres'], 'OR');
            $xor    = isAke($infos['wheres'], 'XOR');

            foreach ($and as $op) {
                $this->where($op, 'AND');
            }

            foreach ($or as $op) {
                $this->where($op, 'OR');
            }

            foreach ($xor as $op) {
                $this->where($op, 'XOR');
            }
            return $this;
        }

        private function parseQuery($query)
        {
            $groupBy    = array();
            $orderBy    = array();
            $orderDir   = array();
            $wheres     = array();
            $limit      = 0;
            $offset     = 0;

            $query      = preg_replace('/\s+/u', ' ', $query);
            $query      = preg_replace('/[\)`\s]from[\(`\s]/ui', ' FROM ', $query);

            if (preg_match('/(limit([0-9\s\,]+)){1}$/ui', $query, $matches)) {
                $query = str_ireplace(Arrays::first($matches), '', $query);
                $tmp = explode(',', $matches[2]);
                if (isset($tmp[1])) {
                    $offset = (int) trim(Arrays::first($tmp));
                    $limit  = (int) trim($tmp[1]);
                } else {
                    $offset = 0;
                    $limit  = (int) trim(Arrays::first($tmp));
                }
            }
            if (preg_match('/(order\sby([^\(\)]+)){1}$/ui', $query, $matches))  {
                $query = str_ireplace(Arrays::first($matches), '', $query);
                $tmp = explode(',', $matches[2]);
                foreach ($tmp as $item) {
                    $item = trim($item);
                    $direct = (
                        mb_strripos($item, ' desc') == (mb_strlen($item) - 5)
                        || mb_strripos($item, '`desc') == (mb_strlen($item) - 5)
                    ) ? 'desc' : 'asc';
                    $item = str_ireplace(array(
                        ' asc',
                        ' desc',
                        '`asc',
                        '`desc',
                        '`'), '', $item);
                    $orderBy[]      = $item;
                    $orderDir[]     = Inflector::upper($direct);
                }
            }
            if (preg_match('/(group\sby([^\(\)]+)){1}$/ui', $query, $matches))  {
                $query = str_ireplace(Arrays::first($matches), '', $query);
                $tmp = explode(',', $matches[2]);
                foreach ($tmp as $item) {
                    $item = trim($item);
                    $groupBy[] = $item;
                }
            }
            $tmp = preg_replace_callback(
                '/\( (?> [^)(]+ | (?R) )+ \)/xui',
                array(
                    $this, 'queryParamsCallback'
                ),
                $query
            );

            $words  = explode(' ', $query);
            $method = Inflector::lower(Arrays::first($words));

            $parts = explode(' where ', Inflector::lower($query));

            if (2 == count($parts)) {
                $whs = Arrays::last($parts);
                $whs = str_replace(
                    array(
                        ' and ',
                        ' or ',
                        ' xor ',
                        ' && ',
                        ' || ',
                        ' | '
                    ),
                    array(
                        ' AND ',
                        ' OR ',
                        ' XOR ',
                        ' AND ',
                        ' OR ',
                        ' XOR '
                    ),
                    $whs
                );

                $wheres['AND'] = strstr($whs, ' AND ') ? explode(' AND ', $whs) : array();
                $wheres['OR']  = strstr($whs, ' OR ') ? explode(' OR ', $whs) : array();
                $wheres['XOR'] = strstr($whs, ' XOR ') ? explode(' XOR ', $whs) : array();
            }

            return array(
                'method'    => $method,
                'wheres'    => $wheres,
                'groupBy'   => $groupBy,
                'orderBy'   => $orderBy,
                'orderDir'  => $orderDir,
                'limit'     => $limit,
                'offset'    => $offset
            );
        }

        private function queryParamsCallback($matches)
        {
            return preg_replace('/./Uui', '*', Arrays::first($matches));
        }
    }
