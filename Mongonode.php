<?php
    namespace Thin;
    use Thin\Database\Collection;

    class Mongonode extends Customize
    {
        private $ns;
        private $entity;
        private $table;
        private $keys;
        private $db;
        private $lastInsertId;
        private $lock;
        private $results;
        private $begin;
        private $transactions   = array();
        private $joins          = array();
        private $wheres         = array();
        private $cache          = false;
        private $debug          = false;
        private $locked         = false;
        private $ttl            = 3600;
        private static $configs = array();
        private static $queries = 0;

        public function __construct($entity, $ns = 'core')
        {
            $this->ns       = $ns;
            $this->entity   = $entity;
            $this->db       = 'dbNode_' . $ns . '_' . $entity;
            $this->lock     = Inflector::camelize('nodeLock_' . $ns . '_' . $entity);
            $this->table    = "dbNode_" . $ns . '::' . $entity;
            $this->keys     = $ns . '::' . $entity . '_keys';
        }

        public static function instance($namespace, $entity)
        {
            $key = sha1($namespace . $entity);
            $has = Instance::has('mongonode', $key);
            if (true === $has) {
                return Instance::get('mongonode', $key);
            } else {
                return Instance::make('mongonode', $key, with(new self($entity, $namespace)));
            }
        }

        public function begin()
        {
            $key = 'begin::nodeDb_' . $this->ns . '_' . $this->entity;
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
            $count = count($this->results);
            $this->reset();
            return $count;
        }

        public function post($save = false)
        {
            return !$save ? $this->create($_POST) : $this->create($_POST)->save();
        }

        public function save($data)
        {
            if (is_object($data)) {
                $data = $data->assoc();
            }

            $this->lock('write');
            if (!Arrays::is($data)) {
                return $data;
            }

            $id = isAke($data, 'id', false);

            if (false === $id) {
                $this->lastInsertId = $id = container()->redis()->incr($this->ns . '_' . $this->entity . '_nodeCount');
                $data['id'] = $id;
                $data['created_at'] = $data['updated_at'] = time();
            } else {
                $old = $this->find($id)->assoc();
                $this->delete($id);
                $data['updated_at'] = time();
                $data = array_merge($old, $data);
            }

            $add = container()->redis()->hset($this->table, $id, json_encode($data));
            $this->all(true);
            $this->unlock('write');
            $this->makeIndexes($data, $id);

            return $this->find($id);
        }

        private function makeIndexes($data, $id)
        {
            $indexes = $this->indexes();
            $fulltextes = $this->fulltextes();

            if (count($fulltextes)) {
                foreach ($fulltextes as $fulltext) {
                    $v = isAke($data, $fulltext, null);
                    if (!empty($v)) {
                        $pattern = "fulltext::$this->ns::$this->entity::" . sha1(Inflector::lower($fulltext)) . "::$id";
                        $prepareFulltext = $this->prepareFulltext($v);
                        container()->redis()->set($pattern, json_encode($prepareFulltext));
                    }
                }
            }

            if (count($indexes)) {
                if (count($data)) {
                    foreach ($indexes as $index) {
                        $v = isAke($data, $index, null);

                        if (!empty($v)) {
                            $v = Arrays::is($v) || is_object($v) ? serialize($v) : $v;
                            $pattern = "index::$this->ns::$this->entity::" . sha1(Inflector::lower($index));
                            $get = container()->redis()->get($pattern);

                            if (strlen($get)) {
                                $tab = json_decode($get, true);
                            } else {
                                $tab = array();
                            }

                            $tab[$id] = $v;
                            container()->redis()->set($pattern, json_encode($tab));
                        }
                    }
                }
            }
        }

        public function delete($id)
        {
            $row = $this->find($id, false);
            $this->lock('delete');
            container()->redis()->hdel($this->table, $id);
            $indexes = $this->indexes();
            $fulltextes = $this->fulltextes();

            if (count($fulltextes)) {
                foreach ($fulltextes as $fulltext) {
                    $pattern = "fulltext::$this->ns::$this->entity::" . sha1(Inflector::lower($fulltext)) . "::$id";
                    container()->redis()->del($pattern);
                }
            }

            if (count($indexes)) {
                foreach ($indexes as $index) {
                    $pattern = "index::$this->ns::$this->entity::" . sha1(Inflector::lower($index));
                    $get = container()->redis()->get($pattern);
                    if (strlen($get)) {
                        $tab = json_decode($het, true);
                    } else {
                        $tab = array();
                    }
                    $newTab = array();
                    if (count($tab)) {
                        foreach ($tab as $k => $v) {
                            if ($k != $id) {
                                $newTab[$k] = $v;
                            }
                        }
                    }
                    container()->redis()->set($pattern, json_encode($newTab));
                }
            }

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

            if (!Arrays::in($id, $tab)) {
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

        private function fulltextes()
        {
            return isAke(isAke(self::$configs, $this->entity), 'fulltexts');
        }

        private function prepareFulltext($text)
        {
            $slugs = explode(' ', Inflector::slug($text, ' '));

            if (count($slugs)) {
                $collection = array();

                foreach ($slugs as $slug) {
                    if (strlen($slug) > 1) {
                        if (!Arrays::in($slug, $collection)) {
                            array_push($collection, $slug);
                        }
                    }
                }

                asort($collection);
            }

            return $collection;
        }

        private function checkValues($data)
        {
            $settings   = isAke(self::$configs, $this->entity);
            $defaults   = isAke($settings, 'defaults');
            $requires   = isAke($settings, 'requires');
            $controls   = isAke($settings, 'controls');
            $uniques    = isAke($settings, 'uniques');

            if (count($data)) {
                $new = array();

                foreach ($data as $k => $v) {
                    $new[$k] = $v;

                    if ($v instanceof Container) {
                        $new[$k] = $v->getId();
                    }
                }
                $data = $new;
            }

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
            foreach ($data as $k => $v) {
                if (is_callable($v)) {
                    unset($data[$k]);
                }
            }

            return $data;
        }

        public function find($id, $object = true)
        {
            $row = container()->redis()->hget($this->table, $id);

            if (strlen($row)) {
                $tab = json_decode($row, true);
                return $object ? $this->toObject($tab) : $tab;
            }

            return $object ? null : array();
        }

        public function findOneBy($field, $value, $object = false)
        {
            return $this->findBy($field, $value, true, $object);
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

        public function only($field)
        {
            $row = $this->first(true);

            return $row instanceof Container
            ? !is_string($row->$field)
                ? $row->$field()
                : $row->$field
            : null;
        }

        public function select($fields, $object = false)
        {
            $collection = array();
            $fields = Arrays::is($fields) ? $fields : array($fields);
            $rows = $this->exec($object);

            if (true === $object) {
                $rows = $rows->rows();
            }

            if (count($rows)) {
                foreach ($rows as $row) {
                    $record = true === $object
                    ? $this->toObject(
                        array(
                            'id' => $row->id
                        )
                    )
                    : array();

                    foreach ($fields as $field) {
                        if (true === $object) {
                            $record->$field = !is_string($row->$field) ? $row->$field() : $row->$field;
                        } else {
                            $record[$field] = ake($field, $row) ? $row[$field] : null;
                        }
                    }

                    array_push($collection, $record);
                }
            }

            return true === $object ? new Collection($collection) : $collection;
        }

        public function first($object = false)
        {
            $res = $this->results;
            $this->reset();

            if (true === $object) {
                return count($res) ? $this->toObject(Arrays::first($res)) : null;
            } else {
                return count($res) ? Arrays::first($res) : array();
            }
        }

        public function last($object = false)
        {
            $res = $this->results;
            $this->reset();

            if (true === $object) {
                return count($res) ? $this->toObject(Arrays::last($res)) : null;
            } else {
                return count($res) ? Arrays::last($res) : array();
            }
        }

        public function execute($object = false)
        {
            return $this->exec($object);
        }

        public function objects()
        {
            return $this->exec(true);
        }

        public function rows()
        {
            return $this->exec();
        }

        public function object()
        {
            return $this->first(true);
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

            $this->reset();

            if (true === $object) {
                $collection = new Collection($collection);
            }

            return $collection;
        }

        private static function structure($ns, $table, $fields)
        {
            $dbt = container()->nbm('nma_table');
            $dbf = container()->nbm('nma_field');
            $dbs = container()->nbm('nma_structure');

            $t = $dbt->where('name = ' . $table)->where('ns = ' . $ns)->first(true);

            if (is_null($t)) {
                $t = $dbt->create(array('name' => $table, 'ns' => $ns))->save();
            }

            if (!is_null($t)) {
                if (count($fields)) {
                    foreach ($fields as $field) {
                        if ('id' != $field) {
                            $f = $dbf->where('name = ' . $field)->first(true);

                            if (is_null($f)) {
                                $f = $dbf->create()->setName($field)->save();
                            }
                            $s = $dbs
                            ->where('table = ' . $t->getId())
                            ->where('field = ' . $f->getId())
                            ->first(true);

                            if (is_null($s)) {
                                $s = $dbs->create()
                                ->setTable($t->getId())
                                ->setField($f->getId())
                                ->setType('varchar')
                                ->setLength(255)
                                ->setIsIndex(false)
                                ->setCanBeNull(true)
                                ->setDefault(null)
                                ->save();
                            }
                        }
                    }
                }
            }
        }

        public static function tables()
        {
            $dbt = container()->nbm('nma_table');
            $rows = container()->redis()->keys('*_nodeCount');
            $tables = array();

            if (count($rows)) {
                foreach ($rows as $row) {
                    $seg = repl('_nodeCount', '', $row);
                    $tab = explode('_', $seg);
                    $ns = Arrays::first($tab);
                    $index = strReplaceFirst($ns . "_", "", $seg);

                    if (!strstr($index, 'nma_')) {
                        $t = $dbt->where('name = ' . $index)->where('ns = ' . $ns)->first(true);

                        if (is_null($t)) {
                            $tableName                  = "dbNode_" . $ns . "::$index";
                            $tables[$index]['count']    = container()->redis()->get($row);
                            $data                       = container()->redis()->hkeys($tableName);

                            if (count($data)) {
                                $first = Arrays::first($data);
                                $first = json_decode(container()->redis()->hget($tableName, $first), true);
                                $fields = array_keys($first);
                                $tables[$index]['fields'] = $fields;
                            } else {
                                $fields = array();
                            }

                            self::structure($ns, $index, $fields);
                        }
                    }
                }
            }

            return $tables;
        }

        public function createTable()
        {
            $check = container()->redis()->get($this->ns . '_' . $this->entity . '_nodeCount');

            if (!strlen($check)) {
                container()->redis()->set($this->ns . '_' . $this->entity . '_nodeCount', 0);
                return $this;
            }

            return false;
        }

        public function dropTable()
        {
            $this->emptyTable();
            container()->redis()->del($this->ns . '_' . $this->entity . '_nodeCount');

            return $this;
        }

        public function emptyTable()
        {
            $rows = $this->fetch()->exec();

            if (count($rows)) {
                foreach ($rows as $row) {
                    $r = $row['id'];
                    unset($row['id']);
                    $key = sha1(serialize($row) . $this->ns . $this->entity);
                    container()->redis()->hdel($this->table, $r);
                    container()->redis()->hdel($this->keys, $key);
                }
            }
            container()->redis()->del($this->ns . '_' . $this->entity . '_nodeCount');

            return $this;
        }

        public function all($force = false)
        {
            $cached = false === $force
            ? $this->cached('RDB_allDb_' . $this->entity)
            : array();

            if (empty($cached)) {
                $rows = container()->redis()->hkeys($this->table);
                $collection = array();

                foreach ($rows as $k => $row) {
                    $data = container()->redis()->hget($this->table, $row);
                    $tab = json_decode($data, true);
                    array_push($collection, $tab);
                }

                $this->cached('RDB_allDb_' . $this->entity, $collection);

                return $collection;
            } else {
                return $cached;
            }
        }

        public function fetch($force = false)
        {
            $this->results = $this->all($force);

            return $this;
        }

        public function reset()
        {
            $this->results          = null;
            $this->begin            = null;
            $this->joins            = array();
            $this->wheres           = array();
            $this->transactions     = array();

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

        public function between($field, $min, $max, $object = false)
        {
            return $this->where($field . ' >= ' . $min)->where($field . ' <= ' . $max)->exec($object);
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

        public function rand($results = array())
        {
            $res = count($results) ? $results : $this->results;
            shuffle($res);
            $this->results = $res;

            return $this;
        }

        public function order($fieldOrder, $orderDirection = 'ASC', $results = array())
        {
            $res = count($results) ? $results : $this->results;

            if (empty($res)) {
                return $this;
            }

            $sortFunc = function($key, $direction) {
                return function ($a, $b) use ($key, $direction) {
                    $valA = isAke($a, $key, null);
                    $valB = isAke($b, $key, null);

                    if ('ASC' == $direction) {
                        return $valA > $valB;
                    } else {
                        return $valA < $valB;
                    }
                };
            };

            if (Arrays::is($fieldOrder) && !Arrays::is($orderDirection)) {
                $t = array();

                foreach ($fieldOrder as $tmpField) {
                    array_push($t, $orderDirection);
                }
                $orderDirection = $t;
            }

            if (!Arrays::is($fieldOrder) && Arrays::is($orderDirection)) {
                $orderDirection = Arrays::first($orderDirection);
            }

            if (Arrays::is($fieldOrder) && Arrays::is($orderDirection)) {
                for ($i = 0 ; $i < count($fieldOrder) ; $i++) {
                    usort($res, $sortFunc($fieldOrder[$i], $orderDirection[$i]));
                }
            } else {
                usort($res, $sortFunc($fieldOrder, $orderDirection));
            }

            $this->results = $res;

            return $this;
        }

        public function andWhere($condition, $results = array())
        {
            return $this->where($condition, 'AND', $results);
        }

        public function orWhere($condition, $results = array())
        {
            return $this->where($condition, 'OR', $results);
        }

        public function xorWhere($condition, $results = array())
        {
            return $this->where($condition, 'XOR', $results);
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

        public function join($table, $field, $fieldFk = null)
        {
            $this->joins[$table] = array();
            array_push($this->joins[$table], array($field, $fieldFk));

            return $this;
        }

        private function intersect($tab1, $tab2)
        {
            $ids1       = array();
            $ids2       = array();
            $collection = array();

            foreach ($tab1 as $row) {
                $id = isAke($row, 'id', null);

                if (strlen($id)) {
                    array_push($ids1, $id);
                }
            }

            foreach ($tab2 as $row) {
                $id = isAke($row, 'id', null);

                if (strlen($id)) {
                    array_push($ids2, $id);
                }
            }

            $sect = array_intersect($ids1, $ids2);

            if (count($sect)) {
                foreach ($sect as $idRow) {
                    array_push($collection, $this->find($idRow, false));
                }
            }

            return $collection;
        }

        public function query($sql)
        {
            if (strstr($sql, ' && ')) {
                $segs = explode(' && ', $sql);

                foreach ($segs as $seg) {
                    $this->where($seg);
                    $sql = str_replace($seg . ' && ', '', $sql);
                }
            }

            if (strstr($sql, ' || ')) {
                $segs = explode(' || ', $sql);

                foreach ($segs as $seg) {
                    $this->where($seg, 'OR');
                    $sql = str_replace($seg . ' || ', '', $sql);
                }
            }

            if (!empty($sql)) {
                $this->where($sql);
            }

            return $this;
        }

        public function trick(Closure $condition, $op = 'AND', $results = array())
        {
            $data = !count($results) ? $this->all() : $results;
            $res = array();

            if (count($data)) {
                foreach ($data as $row) {
                    $resTrick = $condition($row);
                    if (true === $resTrick) {
                        array_push($res, $row);
                    }
                }
            }

            if (!count($this->wheres)) {
                $this->results = array_values($res);
            } else {
                $values = array_values($this->results);

                switch ($op) {
                    case 'AND':
                        $this->results = $this->intersect($values, array_values($res));
                        break;
                    case 'OR':
                        $this->results = array_merge($values, array_values($res));
                        break;
                    case 'XOR':
                        $this->results = array_merge(
                            array_diff(
                                $values,
                                array_values($res),
                                array_diff(
                                    array_values($res),
                                    $values
                                )
                            )
                        );
                        break;
                }
            }
            $this->wheres[] = $condition;
            return $this;
        }

        public function where($condition, $op = 'AND', $results = array())
        {
            if ('dummy' == $this->entity) {
                throw new Exception('A correct entity is mandatory to execute a query. Please use the from method.');
            }
            $res = $this->search($condition, $results, false);
            if (!count($this->wheres)) {
                $this->results = array_values($res);
            } else {
                $values = array_values($this->results);
                switch ($op) {
                    case 'AND':
                        $this->results = $this->intersect($values, array_values($res));
                        break;
                    case 'OR':
                        $this->results = array_merge($values, array_values($res));
                        break;
                    case 'XOR':
                        $this->results = array_merge(
                            array_diff(
                                $values,
                                array_values($res),
                                array_diff(
                                    array_values($res),
                                    $values
                                )
                            )
                        );
                        break;
                }
            }
            $this->wheres[] = $condition;
            return $this;
        }

        public function in($ids, $field = null)
        {
            /* polymorphism */
            $ids = !Arrays::is($ids)
            ? strstr($ids, ',')
                ? explode(',', repl(' ', '', $ids))
                : array($ids)
            : $ids;

            $field = is_null($field) ? $this->pk() : $field;
            return $this->where($field . ' IN (' . implode(',', $ids) . ')');
        }

        public function notIn($ids, $field = null)
        {
            /* polymorphism */
            $ids = !Arrays::is($ids)
            ? strstr($ids, ',')
                ? explode(',', repl(' ', '', $ids))
                : array($ids)
            : $ids;

            $field = is_null($field) ? $this->pk() : $field;
            return $this->where($field . ' NOT IN (' . implode(',', $ids) . ')');
        }

        private function searchFulltextes($field, $value)
        {
            $collection = array();
            $pattern = "fulltext::$this->ns::$this->entity::" . sha1(Inflector::lower($field)) . "::";
            $words = explode(' ', $value);
            $rows = container()->redis()->keys($pattern . '*');
            if (count($rows)) {
                foreach ($rows as $row) {
                    $id = (int) repl($pattern, '', $row);
                    $tabWords = json_decode(container()->redis()->get($row), true);
                    foreach ($tabWords as $tabWord) {
                        foreach ($words as $word) {
                            if (strlen($word) > 1) {
                                if (strstr($tabWord, $word)) {
                                    array_push($collection, $id);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            return $collection;
        }

        private function searchIndexes($field, $op, $value)
        {
            $collection = array();
            $pattern = "index::$this->ns::$this->entity::" . sha1(Inflector::lower($field));
            $get = container()->redis()->get($pattern);
            if (strlen($get)) {
                $tab = json_decode($get, true);
            } else {
                $tab = array();
            }
            if (count($tab)) {
                foreach ($tab as $id => $val) {
                    $check = $this->compare($val, $op, $value);
                    if (true === $check) {
                        array_push($collection, $id);
                    }
                }
            }
            return $collection;
        }

        private function search($condition = null, $results = array(), $populate = true)
        {
            self::$queries++;
            $collection = array();

            $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
            $condition  = repl('NOT IN', 'NOTIN', $condition);
            list($field, $op, $value) = explode(' ', $condition, 3);
            $indexes = $this->indexes();
            $fulltextes = $this->fulltextes();

            if (true === Arrays::in($field, $indexes)) {
                $ids = $this->searchIndexes($field, $op, $value);
                if (count($ids)) {
                    foreach ($ids as $id) {
                        array_push($collection, $this->find($id, false));
                    }
                    if (true === $populate) {
                        $this->results = $collection;
                    }
                    return $collection;
                }
            }

            if ($op == 'LIKE' && Arrays::in($field, $fulltextes)) {
                $ids = $this->searchFulltextes($field, Inflector::slug($value, ' '));
                if (count($ids)) {
                    foreach ($ids as $id) {
                        array_push($collection, $this->find($id, false));
                    }
                    if (true === $populate) {
                        $this->results = $collection;
                    }
                    return $collection;
                }
            }

            $datas      = !count($results) ? $this->all(true) : $results;
            if (empty($condition)) {
                return $datas;
            }

            $keyCache   = sha1('eavRDB_search_' . $condition . serialize($datas) . $this->entity);

            $cached     = $this->cached($keyCache);
            if (empty($cached)) {
                if(count($datas)) {
                    if (strstr($field, '.')) {
                        list($table, $field) = explode('.', $field, 2);
                        if ($table != $this->entity) {
                            $fkFields = isAke($this->joins, $table);
                            if (!empty($fkFields)) {
                                $db = new self($table);
                                list($entityField, $fkField) = Arrays::first($fkFields);
                                $fkField = empty($fkField) ? 'id' : $fkField;
                                foreach ($datas as $tab) {
                                    if (!empty($tab)) {
                                        $joinVal = isAke($tab, $entityField, null);
                                        if (strlen($joinVal)) {
                                            $foreignDatas = $db->where("$fkField = $joinVal")->exec();
                                            foreach ($foreignDatas as $foreignTab) {
                                                if (!empty($foreignTab)) {
                                                    $val = isAke($foreignTab, $field, null);
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
                                        } else {
                                            throw new Exception("The field $entityField has no value and must be not nulled.");
                                        }
                                    }
                                }
                            } else {
                                throw new Exception("The table $table is not correctly joined.");
                            }
                        } else {
                            $condition = repl($this->entity . '.', '', $condition);
                            return $this->search($condition, $results, $populate);
                        }
                    } else {
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

        public function like($field, $str, $op = 'AND')
        {
            return $this->where("$field LIKE " . $str, $op);
        }

        public function firstOrCreate($tab = array())
        {
            if (count($tab)) {
                foreach ($tab as $key => $value) {
                    $this->where("$key = $value");
                }
                $first = $this->first(true);
                if (!is_null($first)) {
                    return $first;
                }
            }
            return $this->create($tab);
        }

        public function replace($compare = array(), $update = array())
        {
            $instance = $this->firstOrCreate($compare);
            return $instance->hydrate($update)->save();
        }

        public function create($tab = array())
        {
            $tab['created_at'] = isAke($tab, 'created_at', time());
            $tab['updated_at'] = isAke($tab, 'updated_at', time());
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
            $settings               = isAke(self::$configs, $this->entity);
            $class                  = $this;
            $class->results         = null;
            $class->wheres          = null;
            $class->joins           = null;
            $class->transactions    = null;

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

            $hydrate = function ($data = array()) use ($obj) {
                $data = empty($data) ? $_POST : $data;
                if (Arrays::isAssoc($data)) {
                    foreach ($data as $k => $v) {
                        if ("true" == $v) {
                            $v = true;
                        } elseif ("false" == $v) {
                            $v = false;
                        } elseif ("null" == $v) {
                            $v = null;
                        }
                        $obj->$k = $v;
                    }
                }
                return $obj;
            };

            $tab = function () use ($obj) {
                return $obj->assoc();
            };

            $asset = function ($field) use ($obj) {
                return '/storage/img/' . $obj->$field;
            };

            $string = function () use ($obj) {
                return $obj->id;
            };

            $id = function () use ($obj) {
                return $obj->getId();
            };

            $exists = function () use ($obj) {
                $id = $obj->getId();
                return !is_null($id);
            };

            $obj->event('save', $save)
            ->event('delete', $delete)
            ->event('date', $date)
            ->event('hydrate', $hydrate)
            ->event('tab', $tab)
            ->event('string', $string)
            ->event('export', $export)
            ->event('id', $id)
            ->event('exists', $exists);

            $functions = isAke($settings, 'functions');

            if (count($functions)) {
                foreach ($functions as $closureName => $callable) {
                    $closureName = lcfirst(Inflector::camelize($closureName));
                    $share = function () use ($obj, $callable) {
                        $args = func_get_args();
                        $args[] = $obj;
                        return call_user_func_array($callable , $args);
                    };
                    $obj->event($closureName, $share);
                }
            }
            return $this->related($obj);
        }

        private function related(Container $obj)
        {
            $fields = array_keys($obj->assoc());
            foreach ($fields as $field) {
                if (endsWith($field, '_id')) {
                    if (is_string($field)) {
                        $value = $obj->$field;
                        if (!is_callable($value)) {
                            $fk = repl('_id', '', $field);
                            $ns = $this->ns;
                            $cb = function() use ($value, $fk, $ns) {
                                $db = container()->nbm($fk, $ns);
                                return $db->find($value);
                            };
                            $obj->event($fk, $cb);

                            $setter = lcfirst(Inflector::camelize("link_$fk"));

                            $cb = function(Container $fkObject) use ($obj, $field, $fk) {
                                $obj->$field = $fkObject->getId();
                                $newCb = function () use ($fkObject) {
                                    return $fkObject;
                                };
                                $obj->event($fk, $newCb);
                                return $obj;
                            };
                            $obj->event($setter, $cb);
                        }
                    }
                }
            }
            return $obj;
        }

        public function model($name)
        {
            return container()->nbm($name);
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

        public function setLocked($bool = true)
        {
            $this->locked = $bool;
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
            if (!strlen($value)) {
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

        public static function configs($entity, $key, $value = null, $cb = null)
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
                if (!Arrays::exists($key . 's', self::$configs[$entity])) {
                    self::$configs[$entity][$key . 's'] = array();
                }
                array_push(self::$configs[$entity][$key . 's'], $value);
            }
            return !is_callable($cb) ? true : $cb();
        }

        public static function makeQuery()
        {
            return new self('dummy');
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
            } elseif (substr($method, 0, 15) == 'findOneObjectBy') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 15)));
                $field = Inflector::lower($uncamelizeMethod);
                $value = Arrays::first($parameters);
                return $this->findBy($field, $value, true, true);
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
                        $c = $this;
                        $cb = function() use ($c) {
                            return $c;
                        };
                        return self::configs($this->entity, $method, Arrays::first($parameters), $cb);
                    } else {
                        return $this->__fire($method, $parameters);
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
            if (true === $this->locked) {
                if (true === $this->debug) {
                    container()->log("lock " . $action);
                }
                $this->waitUnlock($action);
                container()->redis()->set($this->lock, time());
            }
            return $this;
        }

        private function unlock($action)
        {
            if (true === $this->locked) {
                if (true === $this->debug) {
                    container()->log("unlock " . $action);
                }
                container()->redis()->del($this->lock);
            }
            return $this;
        }

        private function waitUnlock($action)
        {
            if (true === $this->locked) {
                if (true === $this->debug) {
                    container()->log("wait " . $action);
                }
                $wait = !is_null(container()->redis()->get($this->lock));
                $i = 1;
                while (true == $wait) {
                    if (1000 == $i) {
                        $this->unlock('forced ' . $action);
                    }
                    usleep(100);
                    $wait = !is_null(container()->redis()->get($this->lock));
                    $i++;
                }
            }
            return $this;
        }

        public function import($csv)
        {
            $datas      = explode("\n", $csv);
            $fields     = explode('**%%**', Arrays::first($datas));
            $count      = count($datas);

            for ($i = 1; $i < $count; $i++) {
                $data   = trim($datas[$i]);
                $row    = array();
                $j      = 0;
                $values = explode('**%%**', $data);
                foreach ($fields as $field) {
                    if ($field != 'id') {
                        $row[$field] = $values[$j];
                        $j++;
                    }
                }
                $record = $this->save($row);
            }
            return $record;
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
            $csv    = implode("\n", $data);
            $name   = date('d_m_Y_H_i_s') . '_' . $this->entity . '_export.csv';
            $file   = TMP_PUBLIC_PATH . DS . $name;
            File::delete($file);
            File::put($file, $csv);
            Utils::go(repl('nma.php', '', URLSITE) . 'tmp/' . $name);
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

        public function now()
        {
            return time();
        }
    }
