<?php
    namespace Thin;
    use Closure;
    use SQLite3;

    class Litedb
    {
        public $settings;
        private $object;
        public $closures    = array();
        private $groupBy;
        private $wheres     = array();
        private $ids        = array();
        private $offset     = 0;
        private $limit      = 0;
        private $tts        = 3600;
        private $fields;
        private $results;
        private $firstQuery = true;
        private $cache      = false;
        private $sum;
        private $avg;
        private $min;
        private $max;
        private $count;

        public function __construct($entity)
        {
            $modelSettings = isAke(Data::$_settings, $entity);
            if (empty($modelSettings)) {
                throw new Exception("Settings for $entity are missing.");
            }

            $modelFields = isAke(Data::$_fields, $entity);
            if (empty($modelFields)) {
                throw new Exception("Fields for $entity are missing.");
            }

            $functions = isAke($modelSettings, "functions");
            if (!empty($functions)) {
                foreach ($functions as $method => $closure) {
                    if ($closure instanceof Closure) {
                        $this->closure($method, $closure);
                    }
                }
                unset($modelSettings['functions']);
            }

            $db = STORAGE_PATH . DS . "$entity.db";
            if (!File::exists($db)) {
                File::put($db, '');
            }

            $session = session("litedb_$entity");
            $session->setData(file($db));

            $this->settings = array(
                'entity'        => $entity,
                'modelSettings' => $modelSettings,
                'modelFields'   => $modelFields,
                'session'       => $session,
                'db'            => $db
            );
        }

        public function closure($name, Closure $closure)
        {
            $this->closures[$name] = $closure;
            return $this;
        }

        public function populate(array $data)
        {
            return $this->newRow($data);
        }

        public function newRow($data = array())
        {
            $object = o(sha1(time() . $this->settings['entity'] . session_id() . Utils::token()));
            $object->thin_litedb = $this;
            $object->id = null;
            if (count($data) && Arrays::isAssoc($data)) {
                foreach ($this->settings['modelFields'] as $field => $infos) {
                    $value = ake($field, $data) ? $data[$field] : null;
                    $object->$field = $value;
                }
            }
            return $object;
        }

        public function all()
        {
            $collection = $this->settings['session']->getData();
            return $this->collection($collection);
        }

        public function collection($tab)
        {
            $collection = array();
            if (count($tab)) {
                foreach ($tab as $i => $row) {
                    $index = $i + 1;
                    $collection[] = $this->row($row, $index);
                }
            }
            return $collection;
        }

        public function save($object)
        {
            $this->object   = $object;
            $fields         = $this->settings['modelFields'];
            $settings       = $this->settings['modelSettings'];
            $session        = $this->settings['session'];
            $db             = $this->settings['db'];
            $checkTuple     = isAke($settings, 'checkTuple');
            $all            = $this->all();
            $create         = false;

            if (!empty($checkTuple)) {
                $this->checkTuple($checkTuple);
            }

            if (count($fields)) {
                foreach ($fields as $field => $info) {
                    $val = $object->$field;
                    if (empty($val)) {
                        if (!Arrays::exists('canBeNull', $info)) {
                            if (!Arrays::exists('default', $info)) {
                                throw new Exception('The field ' . $field . ' cannot be null.');
                            } else {
                                $object->$field = $info['default'];
                            }
                        }
                    } else {
                        if (Arrays::exists('sha1', $info)) {
                            if (!preg_match('/^[0-9a-f]{40}$/i', $val) || strlen($val) != 40) {
                                $object->$field = sha1($val);
                            }
                        } elseif (Arrays::exists('md5', $info)) {
                            if (!preg_match('/^[0-9a-f]{32}$/i', $val) || strlen($val) != 32) {
                                $object->$field = md5($val);
                            }
                        }
                    }
                }
            }
            $object->thin_litedb = null;
            if (null === $object->id) {
                $object->id = $this->nextId();
                $object->date_create = time();
                $create = true;
            }

            $news = array();

            if (count($all)) {
                foreach ($all as $i => $row) {
                    $index = $i + 1;
                    if ($index == $object->id) {
                        $news[] = serialize($this->makeRow($object));
                    } else {
                        $news[] = serialize($this->makeRow($row));
                    }
                }
            }
            if (true === $create) {
                $row = $this->makeRow($object);
                $news[] = serialize($row);
            }
            File::delete($db);
            File::put($db, implode("\n", $news));
            $session->setData(file($db));
            return $object;
        }

        private function makeRow($object)
        {
            $fields = $this->settings['modelFields'];
            $o = $object;
            $new = $this->newRow();
            foreach ($fields as $field => $infos) {
                $get = getter($field);
                $new->$field = $o->$get();
            }
            $new->setThinLitedb(null);
            return $new;
        }

        public function nextId()
        {
            $all = $this->all();
            return count($all) + 1;
        }

        public function delete($object)
        {
            $session    = $this->settings['session'];
            $db         = $this->settings['db'];

            if (null !== $object->id) {
                $all = $this->all();
                $news = array();
                foreach ($all as $i => $row) {
                    $index = $i + 1;
                    if ($index != $object->id) {
                        $row->thin_litedb = null;
                        $news[] = serialize($row);
                    }
                }
                File::delete($db);
                File::put($db, implode("\n", $news));
                $session->setData(file($db));
                return true;
            }
            return false;
        }

        public function row($row, $index)
        {
            $object = unserialize($row);
            $object->id = $index;
            $object->thin_litedb = $this;
            return $object;
        }

        private function checkTuple($field)
        {
            $entity = $this->settings['entity'];
            if (null !== $this->object->id) {
                $tuples = $this->raw("SELECT id FROM $entity WHERE " . $field . " = '" . SQLite3::escapeString($this->object->$field) . "' AND id != '" . $this->object->id . "'");
            } else {
                $tuples = $this->query($field . ' = ' . $this->object->$field);
            }
            if (count($tuples)) {
                throw new Exception("A row exists with value of $field = " . $this->object->$field);
            }
        }

        public function _and($condition)
        {
            return $this->whereAnd($condition);
        }

        public function whereAnd($condition)
        {
            $collection = $this->query($condition);
            $this->resultsAnd($collection);
            return $this;
        }

        public function _or($condition)
        {
            return $this->whereOr($condition);
        }

        public function whereOr($condition)
        {
            $collection = $this->query($condition);
            $this->resultsOr($collection);
            return $this;
        }

        public function _xor($condition)
        {
            return $this->whereXor($condition);
        }

        public function whereXor($condition)
        {
            $collection = $this->query($condition);
            $this->resultsXor($collection);
            return $this;
        }

        public function where($condition)
        {
            return true === $this->firstQuery ? $this->whereOr($condition) : $this->whereAnd($condition);
        }

        public function resultsAnd($resultsAnd)
        {
            if (false === $this->firstQuery) {
                $this->results = array_intersect($this->results, $resultsAnd);
            } else {
                $this->results = $resultsAnd;
            }
            return $this;
        }

        public function resultsOr($resultsOr)
        {
            if (false === $this->firstQuery) {
                $this->results = array_merge($this->results, $resultsOr);
            } else {
                $this->results = $resultsOr;
            }
            return $this;
        }

        public function resultsXor($resultsXor)
        {
            if (false === $this->firstQuery) {
                $this->results = array_merge(array_diff($this->results, $resultsXor), array_diff($resultsXor, $this->results));
            } else {
                $this->results = $resultsXor;
            }
            return $this;
        }

        public function query($condition)
        {
            if (true === $this->cache) {
                $cached = $this->cached($condition);
                if (!empty($cached)) {
                    $this->count = count($cached);
                    return $cached;
                }
            }
            $fields = $this->settings['modelFields'];
            $entity = $this->settings['entity'];
            $datas = $this->all();
            if (!strlen($condition) || !count($datas)) {
                $results        = $datas;
            } else {
                $results = array();
                $db = $this->prepare();
                list($field, $op, $value) = explode(' ', $condition, 3);
                $where = "$field $op '" . SQLite3::escapeString($value) . "'";
                $q = "SELECT id FROM $entity WHERE $where COLLATE NOCASE";var_dump($q);
                $res = $db->query($q);
                while ($row = $res->fetchArray()) {
                    $object = $this->find($row['id']);
                    array_push($results, $object);
                }
            }
            if (true === $this->cache) {
                $this->cached($keyCache, $results);
            }
            return $results;
        }

        public function groupBy($groupBy)
        {
            $this->groupBy = $groupBy;
            return $this;
        }

        public function sum($field)
        {
            return $this->op($field, 'SUM');
        }

        public function min($field)
        {
            return $this->op($field, 'MIN');
        }

        public function max($field)
        {
            return $this->op($field, 'MAX');
        }

        public function avg($field)
        {
            return $this->op($field, 'AVG');
        }

        private function op($field, $op = 'SUM')
        {
            $fields = $this->settings['modelFields'];
            $entity = $this->settings['entity'];
            $datas = $this->all();
            if (!count($datas)) {
                return 0;
            } else {
                $db = $this->prepare();
                $q = "SELECT $op ($field) AS val FROM $entity";
                $res = $db->query($q);
                while ($row = $res->fetchArray()) {
                    return $row['val'];
                }
            }
        }

        public function count()
        {
            return $this->count;
        }

        public function limit($offset, $limit)
        {
            $this->offset = $offset;
            $this->limit = $limit;
            return $this;
        }

        public function raw($sql)
        {
            $db = $this->prepare();

            $res = $db->query($sql);
            $collection = array();

            while ($row = $res->fetchArray()) {
                array_push($collection, $this->find($row['id']));
            }
            return $collection;
        }

        private function prepare()
        {
            $datas = $this->all();
            $fields = $this->settings['modelFields'];
            $entity = $this->settings['entity'];
            $db = new SQLite3(':memory:');
            $q = "DROP TABLE IF EXISTS $entity; CREATE TABLE $entity (id INTEGER PRIMARY KEY, date_create";
            if (count($fields)) {
                foreach ($fields as $field => $infos) {
                    $q .= ", $field";
                }
            }
            $q .= ");";
            $db->exec($q);
            $q = "SELECT id FROM $entity";
            $res = $db->query($q);
            $next = true;
            while ($row = $res->fetchArray() && true === $next) {
                $next = false;
            }
            if (true === $next) {
                $index = 1;
                foreach ($datas as $object) {
                    $q = "INSERT INTO $entity
                    (id, date_create)
                    VALUES ('" . SQLite3::escapeString($index) . "', '" . SQLite3::escapeString($object->date_create) . "')";
                    $db->exec($q);
                    foreach ($fields as $field => $info) {
                        $value = is_object($object->$field) ? 'object' : $object->$field;
                        $q = "UPDATE $entity
                        SET $field = '". SQLite3::escapeString($value) ."'
                        WHERE id = '" . SQLite3::escapeString($object->id) . "'";
                        $db->exec($q);
                    }
                    $index++;
                }
            }
            return $db;
        }

        public function ids($results = null)
        {
            $results = empty($results) ? $this->results : $results;
            $this->ids = array();
            if (count($results)) {
                foreach ($results as $row) {
                    array_push($this->ids, $row->getId());
                }
            }
        }

        public function order($orderField, $orderDirection = 'ASC')
        {
            if (count($this->results) && null !== $orderField) {
                if (Arrays::is($orderField)) {
                    if (count($orderField) == 1) {
                        if (Arrays::is($orderDirection)) {
                            $orderDirection = Arrays::first($orderDirection);
                        }
                        $this->sort(Arrays::first($orderField), $orderDirection);
                    } else {
                        $this->sort($orderField, $orderDirection, true);
                    }
                } else {
                    if (Arrays::is($orderDirection)) {
                        $orderDirection = Arrays::first($orderDirection);
                    }
                    $this->sort($orderField, $orderDirection);
                }
            }
            return $this;
        }

        private function sort($orderField, $orderDirection, $multiSort = false)
        {
            $fieldsEntity = $this->settings['modelFields'];
            $entity = $this->settings['entity'];
            $db = $this->prepare();
            $this->ids();
            $fieldsEntity['date_create'] = array();
            $q = "SELECT id FROM $entity WHERE id IN ('" . implode("', '", $this->ids) . "') ORDER BY ";
            if (false === $multiSort) {
                $type = Arrays::exists('type', $fieldsEntity[$orderField]) ? $fieldsEntity[$orderField]['type'] : null;
                if ('data' == $type) {
                    $q = repl('SELECT id', 'SELECT ' . $entity . '.id', $q);
                    list($dummy, $foreignTable, $foreignField) = $fieldsEntity[$orderField]['contentList'];

                    $fields = Arrays::exists($foreignTable, Data::$_fields)
                    ? Data::$_fields[$foreignTable]
                    : Data::noConfigFields($foreignTable);
                    $query = "DROP TABLE IF EXISTS $foreignTable; CREATE TABLE $foreignTable (id INTEGER PRIMARY KEY, date_create";
                    if (count($fields)) {
                        foreach ($fields as $field => $infos) {
                            $query .= ", $field";
                        }
                    }
                    $query .= ");";
                    $db->exec($query);
                    $lite = new self($foreignTable);
                    $datas = $lite->all();
                    foreach ($datas as $object) {
                        $query = "INSERT INTO $foreignTable (id, date_create) VALUES ('" . SQLite3::escapeString($object->id) . "', '" . SQLite3::escapeString($object->date_create) . "')";
                        $db->exec($query);
                        foreach ($fields as $field => $info) {
                            $value = is_object($object->$field) ? 'object' : $object->$field;
                            $query = "UPDATE $foreignTable SET $field = '". SQLite3::escapeString($value) ."' WHERE id = '" . SQLite3::escapeString($object->id) . "'";
                            $db->exec($query);
                        }
                    }

                    $replace = " LEFT JOIN $foreignTable ON $entity.$orderField = $foreignTable.id  WHERE $entity.";
                    $q = repl(" WHERE ", $replace, $q);
                    $foreignFields = explode(',', $foreignField);
                    for ($i = 0; $i < count($foreignFields); $i++) {
                        $order = $foreignFields[$i];
                        $q .= "$foreignTable.$order $orderDirection, ";
                    }
                    $q = substr($q, 0, -2);
                } else {
                    $q .= "$orderField $orderDirection";
                }
            } else {
                for ($i = 0; $i < count($orderField); $i++) {
                    $order = $orderField[$i];
                    if (Arrays::is($orderDirection)) {
                        $direction = isset($orderDirection[$i]) ? $orderDirection[$i] : 'ASC';
                    } else {
                        $direction = $orderDirection;
                    }
                    $q .= "$order $direction, ";
                }
                $q = substr($q, 0, -2);
            }
            $res = $db->query($q);
            $collection = array();
            while ($row = $res->fetchArray()) {
                array_push($collection, $this->find($row['id']));
            }
            $this->results = $collection;
        }

        public function findBy($field, $value, $one = false)
        {
            return true === $one
            ? $this->where($field . ' = ' . $value)->fetchOne()
            : $this->where($field . ' = ' . $value)->fetch();
        }

        public function fetchAll()
        {
            return $this->where('id > 0');
        }

        public function fetch($results = null)
        {
            $this->count = count($results);
            $results = empty($results) ? $this->results : $results;

            if (count($results)) {
                if (null !== $this->groupBy) {
                    $groupBys   = array();
                    $ever       = array();
                    foreach ($results as $key => $object) {
                        $id = $object->getId();
                        $getter = getter($this->groupBy);
                        $obj = $object->$getter();
                        if ($obj instanceof Container) {
                            $id = $obj->getId();
                        }
                        if (!Arrays::in($id, $ever)) {
                            $groupBys[$key] = $this->find($id);
                            $ever[]         = $id;
                        }
                    }
                    $this->results = $groupBys;
                    $this->order($this->groupBy);
                    $results = $this->results;
                }
                if (0 < $this->limit) {
                    $max    = count($results);
                    $number = $this->limit - $this->offset;
                    if ($number > $max) {
                        $this->offset = $max - $this->limit;
                        if (0 > $this->offset) {
                            $this->offset = 0;
                        }
                        $this->limit = $max;
                    }
                    $results = array_slice($results, $this->offset, $this->limit);
                }
            }
            $this->results = $results;
            return $results;
        }

        public function fetchOne($results = null)
        {
            $results = empty($results) ? $this->results : $results;
            return $this->first($results);
        }

        public function first(array $results)
        {
            if (count($results)) {
                $row = Arrays::first($results);
                if (is_object($row)) {
                    return $row;
                }
            }
            return null;
        }

        public function last(array $results)
        {
            if (count($results)) {
                $row = Arrays::last($results);
                if (is_object($row)) {
                    return $row;
                }
            }
            return null;
        }

        private function cached($what, $data = null)
        {
            $key = sha1($what);
            $file = CACHE_PATH . DS . $key . '_sql';
            if (!empty($data)) {
                File::put($file, serialize($data));
                return $data;
            }
            if (File::exists($file)) {
                $age = time() - filemtime($file);
                if ($age > $this->tts) {
                    File::delete($file);
                } else {
                    return unserialize(fgc($file));
                }
            }
        }

        public function find($id)
        {
            $collection = $this->settings['session']->getData();
            if (isset($collection[$id - 1])) {
            $obj =  unserialize($collection[$id - 1]);
            $obj->id = $id;
            $obj->thin_litedb = $this;
                return $obj;
            }
            return $this->newRow();
        }

        public function __call($method, $args)
        {
            if (substr($method, 0, strlen('findBy')) == 'findBy') {
                $value = Arrays::first($args);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 6)));
                $var = Inflector::lower($uncamelizeMethod);
                return $this->findBy($var, $value);
            } elseif (substr($method, 0, strlen('findOneBy')) == 'findOneBy') {
                $value = Arrays::first($args);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 9)));
                $var = Inflector::lower($uncamelizeMethod);
                return $this->findBy($var, $value, true);
            }
        }

        public function tts($tts = 3600)
        {
            $this->tts = $tts;
            return $this;
        }

        public function cache($bool)
        {
            $this->cache = $bool;
            return $this;
        }
    }
