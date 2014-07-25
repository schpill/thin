<?php
    namespace Thin;
    use mysqli;
    use mysqli_result;

    class Database
    {
        public $db, $database, $table, $query, $offset, $limit, $map = array(), $args = array(), $results = array(), $wheres = array(), $groupBys = array(), $orders = array();
        public static $instances   = array();
        public static $config       = array();

        public function __construct($db, $table, $host = 'localhost', $username = 'root', $password = '')
        {
            $this->args = func_get_args();
            $this->db = new mysqli($host, $username, $password, $db);
            $this->table = $table;
            $this->database = $db;
            $this->map();
        }

        public function __call($fn, $args)
        {
            $method = substr($fn, 0, 3);
            $object = lcfirst(substr($fn, 3));
            if ('get' === $method) {
                $object = Inflector::uncamelize($object);
                return isset($this->$object) ? $this->$object : null;
            } else if ('set' === $method) {
                $object = Inflector::uncamelize($object);
                $this->$object = Arrays::first($args);
                return $this;
            } else if ('has' === $method) {
                $object = Inflector::uncamelize($object);
                return isset($this->$object);
            }
            return null;
        }

        public function __destruct()
        {
            $this->db->close();
        }

        public static function instance($db, $table, $host = 'localhost', $username = 'root', $password = '')
        {
            $key = sha1(serialize(func_get_args()));
            $i = isAke(static::$instances, $key, null);
            if (is_null($i)) {
                $i = new self($db, $table, $host, $username, $password);
                static::$instances[$key] = $i;
            }
            return $i;
        }

        public function db()
        {
            return $this->db;
        }

        public function query($query)
        {
            return $this->db->query($query);
        }

        public function fetch($query = null)
        {
            $query = is_null($query) ? "SELECT $this->database.$this->table.* FROM $this->database.$this->table" : $query;
            $collection = array();
            $res = $this->db->query($query);
            if (is_object($res)) {
                while ($row = $res->fetch_assoc()) {
                    array_push($collection, $row);
                }
                $res->close();
            }
            return $collection;
        }

        public function get()
        {
            $this->results = $this->fetch();
            return $this;
        }

        public function pk()
        {
            return $this->map['pk'];
        }

        public function map()
        {
            $query  = "SHOW COLUMNS FROM $this->database.$this->table";
            $res    = $this->fetch($query);

            $settings   = isAke(self::$config, "$this->database.$this->table");
            $relations  = isAke($settings, 'relations', false);

            if (false === $relations) {
                $relations = array();
                $relationsQuery = "SELECT
                REFERENCED_TABLE_NAME as foreignTable
                FROM information_schema.REFERENTIAL_CONSTRAINTS
                WHERE
                UNIQUE_CONSTRAINT_SCHEMA = '$this->database'
                AND TABLE_NAME = '$this->table'";
                $resRel = $this->fetch($relationsQuery);
                if (count($resRel)) {
                    foreach ($resRel as $rowRel) {
                        array_push($relations, $rowRel['foreignTable']);
                    }
                }
                self::$config["$this->database.$this->table"]['relations'] = $relations;
            }

            $fields = array();
            $keys   = array();
            $pk     = null;

            if (count($res)) {
                foreach ($res as $row) {
                    $fields[$row['Field']] = array(
                        'type' => typeSql($row['Type']),
                        'nullable' => ('yes' == Inflector::lower($row['Null'])) ? true : false
                    );
                    if ($row['Key'] == 'PRI') {
                        $pk = $row['Field'];
                    }
                    if ($row['Key'] != 'PRI' && strlen($row['Key'])) {
                        array_push($keys, $row['Field']);
                    }
                }
            }

            $this->map = array(
                'fields'    => $fields,
                'pk'        => $pk,
                'keys'      => $keys
            );

            if (false === $relations) {
                $relations = array();
                if (count($keys)) {
                    foreach ($keys as $key) {
                        if (strstr($key, '_id')) {
                            array_push($relations, repl('_id', '', $key));
                        }
                    }
                }
                self::$config["$this->database.$this->table"]['relations'] = $relations;
            }
            return $this;
        }

        public function all()
        {
            return $this->fetch();
        }

        public function countAll()
        {
            return count($this->all());
        }

        public function count()
        {
            $count = count($this->results);
            $this->reset();
            return $count;
        }

        public function reset()
        {
            $this->results  = array();
            $this->wheres   = array();
            $this->groupBys = array();
            $this->orders   = array();
            $this->limit    = null;
            $this->offset   = null;
            $this->query    = null;
            return $this;
        }

        public function post()
        {
            return $this->create($_POST);
        }

        public function sql()
        {
            return new Query($this);
        }

        public function save(Container $row)
        {
            $data = $row->assoc();

            $id = isAke($data, $this->map['pk'], null);

            if (strlen($id)) {
                unset($data[$this->map['pk']]);
                return $this->edit($id, $data);
            } else {
                return $this->add($data);
            }
        }

        private function add($data)
        {
            $q = "INSERT INTO $this->database.$this->table SET ";
            foreach ($data as $k => $v) {
                $q .= "$this->database.$this->table.$k = '" . addslashes($v) . "', ";
            }
            $q = substr($q, 0, -2);

            $insert = $this->db->prepare($q);
            $insert->execute();
            $insert->close();

            $data[$this->map['pk']] = $this->db->insert_id;
            return $this->row($data);
        }

        private function edit($id, $data)
        {
            $idData = isAke($data, $this->map['pk'], null);
            if (!is_null($idData)) {
                unset($data[$this->map['pk']]);
            }
            $q = "UPDATE $this->database.$this->table SET ";
            foreach ($data as $k => $v) {
                $q .= "$this->database.$this->table.$k = '" . addslashes($v) . "', ";
            }
            $q = substr($q, 0, -2);
            $q .= " WHERE $this->database.$this->table." . $this->map['pk'] . " = '" . addslashes($id) . "'";

            $update = $this->db->prepare($q);
            $update->execute();
            $update->close();

            $data[$this->map['pk']] = $id;
            return $this->row($data);
        }

        public function delete($id)
        {
            $q = "DELETE FROM $this->database.$this->table WHERE $this->database.$this->table." . $this->map['pk'] . " = '" . addslashes($id) . "'";
            $this->db->query($q);
            return $this;
        }

        public function update($update, $where = null)
        {
            $res = !empty($where) ? $this->where($where)->exec() : $this->all();
            if (count($res)) {
                list($field, $newValue) = explode(' = ', $update, 2);
                foreach ($res as $row) {
                    $val = isAke($row, $field, null);
                    if ($val != $newValue) {
                        $row[$field] = $newValue;
                        $this->edit($row[$this->map['pk']], $row);
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
            $res = !empty($where) ? $this->where($where)->exec() : $this->all();
            if (count($res)) {
                foreach ($res as $row) {
                    $this->delete($row[$this->map['pk']]);
                }
            }
            return $this;
        }

        public function find($id, $object = true)
        {
            $q = "SELECT * FROM $this->database.$this->table WHERE $this->database.$this->table." . $this->map['pk'] . " = '" . addslashes($id) . "'";
            $res = $this->fetch($q);
            if (count($res)) {
                $row = Arrays::first($res);
                return $object ? $this->row($row) : $row;
            }
            return $object ? null : array();
        }

        public function findBy($field, $value, $one = false, $object = false)
        {
            $q = "SELECT * FROM $this->database.$this->table WHERE $this->database.$this->table." . $field . " = '" . addslashes($value) . "'";
            $res = $this->fetch($q);
            if (count($res) && true === $one) {
                return $object ? $this->row(Arrays::first($res)) : Arrays::first($res);
            }
            if (!count($res) && true === $one && true === $object) {
                return null;
            }
            return $this->exec($object, $res);
        }

        public function first($object = false)
        {
            $res = $this->results;
            $this->reset();
            if (true === $object) {
                return count($res) ? $this->row(Arrays::first($res)) : null;
            } else {
                return count($res) ? Arrays::first($res) : array();
            }
        }

        public function last($object = false)
        {
            $res = $this->results;
            $this->reset();
            if (true === $object) {
                return count($res) ? $this->row(Arrays::last($res)) : null;
            } else {
                return count($res) ? Arrays::last($res) : array();
            }
        }

        public function exec($object = false, $results = null)
        {
            if (is_null($results)) {
                $this->makeResults();
            } else {
                $this->results = $results;
            }
            $collection = array();
            if (count($this->results)) {
                foreach ($this->results as $row) {
                    $item = $object ? $this->row($row) : $row;
                    array_push($collection, $item);
                }
            }
            $this->reset();
            if (!count($collection) && true === $object) {
                return null;
            }
            return $collection;
        }

        private function makeResults()
        {
            $query = "SELECT $this->database.$this->table.* FROM $this->database.$this->table WHERE ";
            if (count($this->wheres)) {
                $first = true;
                foreach ($this->wheres as $where) {
                    list($op, $condition) = $where;
                    if (false === $first) {
                        $query .= " $op $condition";
                    } else {
                        $query .= $condition;
                    }
                    $first = false;
                }
            } else {
                $query .= '1 = 1';
            }

            if (count($this->groupBys)) {
                $query .= ' GROUP BY ';
                $first = true;
                foreach ($this->groupBys as $groupBy) {
                    if (false === $first) {
                        $query .= ", $this->database.$this->table.$groupBy";
                    } else {
                        $query .= $groupBy;
                    }
                    $first = false;
                }
            }

            if (count($this->orders)) {
                $query .= ' ORDER BY ';
                $first = true;
                foreach ($this->orders as $order) {
                    list($field, $direction) = $order;
                    if (false === $first) {
                        $query .= ", $this->database.$this->table.$field $direction";
                    } else {
                        $query .= "$this->database.$this->table.$field $direction";
                    }
                    $first = false;
                }
            }

            if (isset($this->limit)) {
                $offset = isset($this->offset) ? $this->offset : 0;
                $query .= ' LIMIT ' . $offset . ', ' . $this->limit;
            }

            $this->query = $query;
            $this->results = $this->fetch($query);
            return $this;
        }

        public function getQuery()
        {
            return $this->query;
        }

        public function order($field, $direction = 'ASC')
        {
            $direction = Inflector::upper($direction);
            $this->orders[] = array($field, $direction);
            return $this;
        }

        public function groupBy($field)
        {
            $this->groupBys[] = $field;
            return $this;
        }

        public function limit($limit, $offset = 0)
        {
            $this->limit = $limit;
            $this->offset = $offset;
            return $this;
        }

        public function andWhere($condition)
        {
            return $this->where($condition);
        }

        public function orWhere($condition)
        {
            return $this->where($condition, 'OR');
        }

        public function xorWhere($condition)
        {
            return $this->where($condition, 'XOR');
        }

        public function where($condition, $op = 'AND')
        {
            $this->wheres[] = array($op, $condition);
            return $this;
        }

        private function operand($type, $field)
        {
            $q = "SELECT $type($this->database.$this->table.$field) AS $type FROM $this->database.$this->table";
            $res = $this->fetch($q);
            return count($res) ? Arrays::first($res) : 0;
        }

        public function sum($field)
        {
            return $this->operand('SUM', $field);
        }

        public function avg($field)
        {
            return $this->operand('AVG', $field);
        }

        public function min($field)
        {
            return $this->operand('MIN', $field);
        }

        public function max($field)
        {
            return $this->operand('MAX', $field);
        }

        public function row($tab = array())
        {
            $o = new Container;
            $o->populate($tab);
            return $this->closures($o);
        }

        private function closures($obj)
        {
            $params = $this->args;

            $extend = function ($name, $callable) use ($obj, $params) {
                if (is_callable($callable)) {
                    list($db, $table, $host, $username, $password) = $params;
                    $db = Database::instance($db, $table, $host, $username, $password);
                    $share = function () use ($obj, $callable) {
                        $args = func_get_args();
                        $args[] = $obj;
                        $args[] = $db;
                        return call_user_func_array($callable , $args);
                    };
                    $obj->event($name, $share);
                }
            };

            $save = function () use ($obj, $params) {
                list($db, $table, $host, $username, $password) = $params;
                $db = Database::instance($db, $table, $host, $username, $password);
                return $db->save($obj);
            };

            $db = function () use ($params) {
                list($db, $table, $host, $username, $password) = $params;
                return Database::instance($db, $table, $host, $username, $password);
            };

            $query = function () use ($params) {
                list($db, $table, $host, $username, $password) = $params;
                return new Query(Database::instance($db, $table, $host, $username, $password));
            };

            $delete = function () use ($obj, $params) {
                list($db, $table, $host, $username, $password) = $params;
                $db = Database::instance($db, $table, $host, $username, $password);
                $pk = $db->pk();
                return $db->delete($obj->$pk);
            };

            $obj->event('save', $save)
            ->event('delete', $delete)
            ->event('extend', $extend);

            list($db, $table, $host, $username, $password) = $params;
            $settings   = isAke(self::$config, "$db.$table");
            $functions  = isAke($settings, 'functions');

            if (count($functions)) {
                foreach ($functions as $closureName => $callable) {
                    $closureName = lcfirst(Inflector::camelize($closureName));
                    $share = function () use ($obj, $params, $callable) {
                        list($db, $table, $host, $username, $password) = $params;
                        $args = func_get_args();
                        $args[] = $obj;
                        $args[] = Database::instance($db, $table, $host, $username, $password);
                        return call_user_func_array($callable , $args);
                    };
                    $obj->event($closureName, $share);
                }
            }
            return $this->related($obj);
        }

        private function related($obj)
        {
            $settings   = isAke(self::$config, "$this->database.$this->table");
            $relations  = isAke($settings, 'relations');
            $params     = $this->args;
            if (count($relations)) {
                foreach ($relations as $relation) {
                    $field = $relation . '_id';
                    if (isset($obj->$field)) {
                        $value = $obj->$field;
                        if (!is_callable($value)) {
                            $fk = $tableFk = $relation;
                            $cb = function () use ($value, $tableFk, $params) {
                                list($database, $table, $host, $username, $password) = $params;
                                $db = Database::instance($database, $tableFk, $host, $username, $password);
                                if ($db) {
                                    return $db->find($value);
                                }
                                return null;
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

        public function extend($name, $callable)
        {
            $params = $this->args;
            list($db, $table, $host, $username, $password) = $params;

            $settings   = isAke(self::$config, "$db.$table");
            $functions  = isAke($settings, 'functions');

            $functions[$name] = $callable;

            self::$config["$db.$table"]['functions'] = $functions;
            return $this;
        }
    }
