<?php
    namespace Thin;
    class Querydata
    {
        private $type;
        private $groupBy;
        private $wheres     = array();
        private $offset     = 0;
        private $limit      = 0;
        private $fields;
        private $settings;
        private $results;
        private $firstQuery = true;
        private $cache      = false;
        private $sum;
        private $avg;
        private $min;
        private $max;

        public function __construct($type = null, $results = array())
        {
            if (!empty($type)) {
                return $this->factory($type, $results);
            }
        }

        public function factory($type, $results = array())
        {
            $settings   = Arrays::exists($type, Data::$_settings)  ? Data::$_settings[$type]   : Data::defaultConfig($type);
            $fields     = Arrays::exists($type, Data::$_fields)    ? Data::$_fields[$type]     : Data::noConfigFields($type);

            $this->type     = $type;
            $this->fields   = $fields;
            $this->settings = $settings;
            $this->results  = $results;
            Data::db($type);
            return $this;
        }

        public function all()
        {
            $queryKey   = sha1($this->type . 'getAll');
            $cache      = Data::cache($this->type, $queryKey);

            if (!empty($cache) && true === $this->cache) {
                $this->results = $cache;
                return $this;
            }

            $datas      = Data::getAll($this->type);
            if (count($datas)) {
                foreach ($datas as $path) {
                    $object = Data::getObject($path);
                    array_push($this->results, $object->getId());
                }
            }
            $cache = Data::cache($this->type, $queryKey, $this->results);
            return $this;
        }

        public function query($condition)
        {
            $this->firstQuery = false;
            Data::_incQueries(Data::_getTime());
            $queryKey   = sha1(serialize($condition) . 'QueryData');
            $cache      = Data::cache($this->type, $queryKey);

            if (!empty($cache) && true === $this->cache) {
                return $cache;
            }
            $this->wheres[] = $condition;
            if (is_string($condition)) {
                $res = Data::query($this->type, $condition);
                $collection = array();
                if (count($res)) {
                    foreach ($res as $row) {
                        if (is_string($row)) {
                            $tab = explode(DS, $row);
                            $id = repl(".data", '', Arrays::last($tab));
                        } else {
                            if (is_object($row)) {
                                $id = $row->id;
                            }
                        }
                        $collection[] = $id;
                    }
                }
            } else {
                if (Arrays::isArray($condition)) {
                    $collection = $condition;
                } else {
                    $collection = array();
                }
            }
            $cache = Data::cache($this->type, $queryKey, $collection);
            return $collection;
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

        public function db($table = null)
        {
            $table = !empty($table) ? $table : $this->type;
            if (!Arrays::exists($table, Data::$_db)) {
                Data::db($table);
            }
            $fields = Arrays::exists($table, Data::$_fields)
            ? Data::$_fields[$table]
            : Data::noConfigFields($table);

            $datas = Data::getAll($table);
            $q = "SELECT * FROM " . $table . " WHERE id IS NOT NULL";
            $res = Data::$_db[$table]->query($q);
            $next = true;
            while ($row = $res->fetchArray() && true === $next) {
                $next = false;
            }
            if (true === $next) {
                foreach ($datas as $tmpObject) {
                    $object = Data::getObject($tmpObject, $table);
                    $q = "INSERT INTO $table (id, date_create) VALUES ('" . \SQLite3::escapeString($object->id) . "', '" . \SQLite3::escapeString($object->date_create) . "')";
                    Data::$_db[$table]->exec($q);
                    foreach ($fields as $field => $info) {
                        $q = "UPDATE $table SET $field = '". \SQLite3::escapeString($object->$field) ."' WHERE id = '" . \SQLite3::escapeString($object->id) . "'";
                        Data::$_db[$table]->exec($q);
                    }
                }
            }
        }

        public function order($orderField, $orderDirection = 'ASC')
        {
            if (count($this->results) && null !== $orderField) {
                $this->db();
                $queryKey   = sha1(serialize($this->wheres) . serialize($orderField) . serialize($orderDirection));
                $cache      = Data::cache($this->type, $queryKey);

                if (!empty($cache)) {
                    $this->results = $cache;
                    return $this;
                }

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
            $db = new \SQLite3(':memory:');
            $fields = Arrays::exists($this->type, Data::$_fields)
            ? Data::$_fields[$this->type]
            : Data::noConfigFields($this->type);
            $q = "DROP TABLE IF EXISTS $this->type; CREATE TABLE $this->type (id VARCHAR PRIMARY KEY, date_create";
            if (count($fields)) {
                foreach ($fields as $field => $infos) {
                    $q .= ", $field";
                }
            }
            $q .= ");";
            $db->exec($q);
            $datas = Data::getAll($this->type);
            foreach ($datas as $tmpObject) {
                $object = Data::getObject($tmpObject, $this->type);
                $q = "INSERT INTO $this->type (id, date_create) VALUES ('" . \SQLite3::escapeString($object->id) . "', '" . \SQLite3::escapeString($object->date_create) . "')";
                $db->exec($q);
                foreach ($fields as $field => $info) {
                    $value = is_object($object->$field) ? 'object' : $object->$field;
                    $q = "UPDATE $this->type SET $field = '". \SQLite3::escapeString($value) ."' WHERE id = '" . \SQLite3::escapeString($object->id) . "'";
                    $db->exec($q);
                }
            }
            $this->fields['date_create'] = array();
            $q = "SELECT id FROM $this->type WHERE id IN ('" . implode("', '", $this->results) . "') ORDER BY ";
            if (false === $multiSort) {
                $type = Arrays::exists('type', $this->fields[$orderField]) ? $this->fields[$orderField]['type'] : null;
                if ('data' == $type) {
                    $q = repl('SELECT id', 'SELECT ' . $this->type . '.id', $q);
                    list($dummy, $foreignTable, $foreignField) = $this->fields[$orderField]['contentList'];

                    $fields = Arrays::exists($foreignTable, Data::$_fields)
                    ? Data::$_fields[$foreignTable]
                    : Data::noConfigFields($foreignTable);
                    $query = "DROP TABLE IF EXISTS $foreignTable; CREATE TABLE $foreignTable (id VARCHAR PRIMARY KEY, date_create";
                    if (count($fields)) {
                        foreach ($fields as $field => $infos) {
                            $query .= ", $field";
                        }
                    }
                    $query .= ");";
                    $db->exec($query);
                    $datas = Data::getAll($foreignTable);
                    foreach ($datas as $tmpObject) {
                        $object = Data::getObject($tmpObject, $foreignTable);
                        $query = "INSERT INTO $foreignTable (id, date_create) VALUES ('" . \SQLite3::escapeString($object->id) . "', '" . \SQLite3::escapeString($object->date_create) . "')";
                        $db->exec($query);
                        foreach ($fields as $field => $info) {
                            $value = is_object($object->$field) ? 'object' : $object->$field;
                            $query = "UPDATE $foreignTable SET $field = '". \SQLite3::escapeString($value) ."' WHERE id = '" . \SQLite3::escapeString($object->id) . "'";
                            $db->exec($query);
                        }
                    }

                    $replace = " LEFT JOIN $foreignTable ON $this->type.$orderField = $foreignTable.id  WHERE $this->type.";
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
                array_push($collection, $row['id']);
            }
            $this->results = $collection;
            // return $this;
            // $sort = array();
            // foreach($this->results as $id) {
            //     $object                  = (is_string($id)) ? Data::getById($this->type, $id) : $id;
            //     $sort['id'][]            = $object->id;
            //     $sort['date_create'][]   = $object->date_create;

            //     foreach ($this->fields as $k => $infos) {
            //         $value      = isset($object->$k) ? $object->$k : null;
            //         $type = Arrays::exists('type', $infos) ? $infos['type'] : null;
            //         if ('data' == $type) {
            //             list($dummy, $foreignTable, $foreignField) = $infos['contentList'];
            //             $obj = Data::getById($foreignTable, $value);
            //             $foreignFields = explode(',', $foreignField);
            //             $val = array();
            //             foreach ($foreignFields as $ff) {
            //                 $val[] = isset($obj->$ff) ? $obj->$ff : null;
            //             }
            //             $value = count($val) == 1 ? Arrays::first($val) : implode(' ', $val);
            //         }
            //         $sort[$k][] = $value;

            //     }
            // }
            // $asort = array();
            // foreach ($sort as $k => $rows) {
            //     for ($i = 0 ; $i < count($rows) ; $i++) {
            //         if (empty($$k) || is_string($$k) || is_object($$k)) {
            //             $$k = array();
            //         }
            //         $asort[$i][$k] = $rows[$i];
            //         array_push($$k, $rows[$i]);
            //     }
            // }

            // if (false === $multiSort) {
            //     if ('ASC' == Inflector::upper($orderDirection)) {
            //         array_multisort($$orderField, SORT_ASC, $asort);
            //     } else {
            //         array_multisort($$orderField, SORT_DESC, $asort);
            //     }
            // } else {
            //     if (count($orderField) == 2) {
            //         $first = Arrays::first($orderField);
            //         $second = Arrays::last($orderField);
            //         $tab = array();
            //         if (Arrays::is($orderDirection)) {
            //             $tab = $orderDirection;
            //             if (!isset($tab[1])) {
            //                 $tab[1] = Arrays::first($tab);
            //             }
            //         } else {
            //             $tab[0] = $tab[1] = $orderDirection;
            //         }
            //         $orderDirection = $tab;
            //         if ('ASC' == Inflector::upper(Arrays::first($orderDirection)) && 'ASC' == Inflector::upper(end($orderDirection))) {
            //             array_multisort($$first, SORT_ASC, $$second, SORT_ASC, $asort);
            //         } elseif ('DESC' == Inflector::upper(Arrays::first($orderDirection)) && 'ASC' == Inflector::upper(end($orderDirection))) {
            //             array_multisort($$first, SORT_DESC, $$second, SORT_ASC, $asort);
            //         } elseif ('DESC' == Inflector::upper(Arrays::first($orderDirection)) && 'DESC' == Inflector::upper(end($orderDirection))) {
            //             array_multisort($$first, SORT_DESC, $$second, SORT_DESC, $asort);
            //         } elseif ('ASC' == Inflector::upper(Arrays::first($orderDirection)) && 'DESC' == Inflector::upper(end($orderDirection))) {
            //             array_multisort($$first, SORT_ASC, $$second, SORT_DESC, $asort);
            //         }
            //     }
            // }

            // $collection = array();
            // foreach ($asort as $k => $row) {
            //     $tmpId = $row['id'];
            //     array_push($collection, $tmpId);
            // }
            // $this->results = $collection;
        }

        public function offset($offset)
        {
            $this->offset = $offset;
            return $this;
        }

        public function groupBy($groupBy)
        {
            $this->groupBy = $groupBy;
            return $this;
        }

        public function limit($limit)
        {
            $this->limit = $limit;
            return $this;
        }

        public function sum($field)
        {
            $this->sum = $field;
            return $this;
        }

        public function avg($field)
        {
            $this->avg = $field;
            return $this;
        }

        public function min($field)
        {
            $this->min = $field;
            return $this;
        }

        public function max($field)
        {
            $this->max = $field;
            return $this;
        }

        public function sub()
        {
            return $this->results;
        }

        public function count($results = null)
        {
            $results = null !== $results ? $results : $this->results;
            return count($results);
        }

        public function fetch($results = null)
        {
            return $this->get($results);
        }

        public function getAll()
        {
            $this->cache = false;
            return $this->all();
        }

        public function findBy($field, $value, $one = false)
        {
            return true === $one
            ? $this->where($field . ' = ' . $value)->getOne()
            : $this->where($field . ' = ' . $value)->get();
        }

        public function find($id)
        {
            return Data::getById($this->type, $id);
        }

        public function getOne($results = null)
        {
            return $this->first($this->get($results));
        }

        public function table($table, array $fields, $settings = array())
        {
            $table = Inflector::lower($table);
            if (!Arrays::in($table, $this->getTables())) {
                data($table, $fields, $settings);
                return $this->factory($table);
            }
            return $this;
        }

        public function getTables()
        {
            $tables = array();
            foreach (Data::$_fields as $table => $fields) {
                array_push($tables, $table);
            }
            asort($tables);
            return $tables;
        }

        public function getFields()
        {
            $fields = array();
            foreach ($this->fields as $field => $params) {
                array_push($fields, $field);
            }
            return $fields;
        }

        public function insert(array $data)
        {
            if (count($data)) {
                foreach ($data as $row) {
                    $object = Data::newOne($this->type, $row);
                }
            }
            return $this;
        }

        public function join($fkTable, $condition)
        {
            $joinResults = array();
            $db = new \SQLite3(':memory:');
            $results = count($this->results)
            ? $this->results
            : $this->all()->sub();

            $fields = Arrays::exists($fkTable, Data::$_fields)
            ? Data::$_fields[$fkTable]
            : Data::noConfigFields($fkTable);
            $q = "DROP TABLE IF EXISTS $fkTable; CREATE TABLE $fkTable (id VARCHAR PRIMARY KEY, date_create";
            if (count($fields)) {
                foreach ($fields as $field => $infos) {
                    $q .= ", $field";
                }
            }
            $q .= ");";
            $db->exec($q);
            $datas = Data::getAll($fkTable);
            foreach ($datas as $tmpObject) {
                $object = Data::getObject($tmpObject, $fkTable);
                $q = "INSERT INTO $fkTable (id, date_create) VALUES ('" . \SQLite3::escapeString($object->id) . "', '" . \SQLite3::escapeString($object->date_create) . "')";
                $db->exec($q);
                foreach ($fields as $field => $info) {
                    $q = "UPDATE $fkTable SET $field = '". \SQLite3::escapeString($object->$field) ."' WHERE id = '" . \SQLite3::escapeString($object->id) . "'";
                    $db->exec($q);
                }
            }

            $fields = Arrays::exists($this->type, Data::$_fields)
            ? Data::$_fields[$this->type]
            : Data::noConfigFields($this->type);
            $q = "DROP TABLE IF EXISTS $this->type; CREATE TABLE $this->type (id VARCHAR PRIMARY KEY, date_create";
            if (count($fields)) {
                foreach ($fields as $field => $infos) {
                    $q .= ", $field";
                }
            }
            $q .= ");";
            $db->exec($q);
            $datas = Data::getAll($this->type);
            foreach ($datas as $tmpObject) {
                $object = Data::getObject($tmpObject, $this->type);
                $q = "INSERT INTO $this->type (id, date_create) VALUES ('" . \SQLite3::escapeString($object->id) . "', '" . \SQLite3::escapeString($object->date_create) . "')";
                $db->exec($q);
                foreach ($fields as $field => $info) {
                    $q = "UPDATE $this->type SET $field = '". \SQLite3::escapeString($object->$field) ."' WHERE id = '" . \SQLite3::escapeString($object->id) . "'";
                    $db->exec($q);
                }
            }

            list($field, $op, $value) = explode(' ', $condition, 3);
            $where = "$fkTable.$field $op '" . \SQLite3::escapeString($value) . "'";

            $q = "SELECT $this->type.id
            FROM $this->type
            LEFT JOIN $fkTable ON $this->type.$fkTable = $fkTable.id
            WHERE $where
            ";
            $res = $db->query($q);
            while ($row = $res->fetchArray()) {
                array_push($joinResults, $row['id']);
            }
            if (count($joinResults)) {
                $this->results = $joinResults;
            }
            return $this;
        }

        public function update(array $params, $results = array())
        {
            $resultsToUpdate = !empty($results)
            ? $results
            : $this->results;
            if (count($resultsToDelete) && count($params)) {
                foreach ($resultsGet as $key => $id) {
                    $object = Data::getById($this->type, $id);
                    foreach ($params as $k => $v) {
                        $object->$k = $v;
                    }
                    $object->save();
                }
            }
            return $this;
        }

        public function delete($results = array())
        {
            $resultsToDelete = !empty($results)
            ? $results
            : $this->results;
            if (count($resultsToDelete)) {
                foreach ($resultsGet as $key => $id) {
                    $object = Data::getById($this->type, $id);
                    $object->delete();
                }
            }
            return $this;
        }

        public function get($results = null)
        {
            $resultsGet = null !== $results ? $results : $this->results;
            $queryKey   = sha1(serialize($this->wheres) . serialize($resultsGet));
            $cache      = Data::cache($this->type, $queryKey);

            if (!empty($cache) && true === $this->cache) {
                return $cache;
            }

            if (count($resultsGet)) {
                if (null !== $this->groupBy) {
                    $groupBys   = array();
                    $ever       = array();
                    foreach ($resultsGet as $key => $id) {
                        $object = Data::getById($this->type, $id);
                        $getter = getter($this->groupBy);
                        $obj = $object->$getter();
                        if ($obj instanceof Container) {
                            $obj = $obj->getId();
                        }
                        if (!Arrays::in($obj, $ever)) {
                            $groupBys[$key] = $id;
                            $ever[]         = $obj;
                        }
                    }
                    $this->results = $groupBys;
                    $this->order($this->groupBy);
                    $resultsGet = $this->results;
                }

                if (0 < $this->limit) {
                    $max    = count($resultsGet);
                    $number = $this->limit - $this->offset;
                    if ($number > $max) {
                        $this->offset = $max - $this->limit;
                        if (0 > $this->offset) {
                            $this->offset = 0;
                        }
                        $this->limit = $max;
                    }
                    $resultsGet = array_slice($resultsGet, $this->offset, $this->limit);
                }
            }
            $collection = array();
            if (count($resultsGet)) {
                $_sum   = 0;
                $_avg   = 0;
                $_min   = 0;
                $_max   = 0;
                $first  = true;
                foreach ($resultsGet as $key => $id) {
                    $object = Data::getById($this->type, $id);

                    if (null !== $this->sum) {
                        $getter = getter($this->sum);
                        $_sum += $object->$getter();
                    }

                    if (null !== $this->avg) {
                        $getter = getter($this->avg);
                        $_avg += $object->$getter();
                    }

                    if (null !== $this->min) {
                        $getter = getter($this->min);
                        if (true === $first) {
                            $_min = $object->$getter();
                        } else {
                            $_min = $object->$getter() < $_min
                            ? $object->$getter()
                            : $_min;
                        }
                    }

                    if (null !== $this->max) {
                        $getter = getter($this->max);
                        if (true === $first) {
                            $_max = $object->$getter();
                        } else {
                            $_max = $object->$getter() > $_max
                            ? $object->$getter()
                            : $_max;
                        }
                    }
                    $collection[]   = $object;
                    $first = false;
                }
            }

            if (null !== $this->min) {
                $collection = $_min;
            }
            if (null !== $this->max) {
                $collection = $_max;
            }
            if (null !== $this->sum) {
                $collection = $_sum;
            }
            if (null !== $this->avg) {
                $collection = $_avg / count($collection);
            }

            $cache = Data::cache($this->type, $queryKey, $collection);
            return $collection;
        }

        public function create($data = array())
        {
            return Data::newOne($this->type, $data);
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

        public function getCache()
        {
            return $this->cache;
        }

        public function setCache($bool)
        {
            $this->cache = $bool;
            return $this;
        }

        public static function __callstatic($method, $parameters)
        {
            array_unshift($parameters, $this->type);
            return call_user_func_array(array("Thin\\Data", $method), $parameters);
        }

        public function __call($method, $parameters)
        {
            if (substr($method, 0, 6) == 'findBy') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 6)));
                $field = Inflector::lower($uncamelizeMethod);
                $value = Arrays::first();
                return $this->findBy($field, $value);
            } elseif (substr($method, 0, 9) == 'findOneBy') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 9)));
                $field = Inflector::lower($uncamelizeMethod);
                $value = Arrays::first();
                return $this->findBy($field, $value, true);
            }
        }

        public function __toString()
        {
            return $this->type;
        }
    }
