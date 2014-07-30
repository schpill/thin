<?php
    namespace Thin;
    use Closure;
    use mysqli;
    use mysqli_result;
    use Thin\Database\Collection;

    class Database
    {
        public $db,
        $database,
        $table,
        $query,
        $offset,
        $limit,
        $map        = array(),
        $args       = array(),
        $results    = array(),
        $wheres     = array(),
        $fields     = array(),
        $as         = array(),
        $joins      = array(),
        $havings    = array(),
        $groupBys   = array(),
        $orders     = array();
        public static $instances    = array();
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
            $method = substr($fn, 0, 5);
            $object = lcfirst(substr($fn, 5));
            if ('where' == $method) {
                $field = Inflector::uncamelize($object);
                return $this->where($field . ' ' . Arrays::first($args));
            }
            $method = substr($fn, 0, 7);
            $object = lcfirst(substr($fn, 7));
            if ('orWhere' == $method) {
                $field = Inflector::uncamelize($object);
                return $this->where($field . ' ' . Arrays::first($args), 'OR');
            }
            $method = substr($fn, 0, 8);
            $object = lcfirst(substr($fn, 8));
            if ('xorWhere' == $method) {
                $field = Inflector::uncamelize($object);
                return $this->where($field . ' ' . Arrays::first($args), 'XOR');
            } elseif('andWhere' == $method) {
                $field = Inflector::uncamelize($object);
                return $this->where($field . ' ' . Arrays::first($args));
            }
            throw new Exception("Method '$fn' is unknown.");
        }

        public function __destruct()
        {
            $this->db->close();
        }

        public static function instance($db, $table, $host = 'localhost', $username = 'root', $password = '')
        {
            $key    = sha1(serialize(func_get_args()));
            $i      = isAke(static::$instances, $key, null);
            if (is_null($i)) {
                $i  = new self($db, $table, $host, $username, $password);
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

        public function fetch($query = null, $object = false)
        {
            $query = is_null($query) ? "SELECT $this->database.$this->table.* FROM $this->database.$this->table" : $query;
            $collection = array();
            $res = $this->db->query($query);
            if (is_object($res)) {
                while ($row = $res->fetch_assoc()) {
                    if (true === $object) {
                        array_push($collection, $this->row($row));
                    } else {
                        array_push($collection, $row);
                    }
                }
                $res->close();
            } else {
                /* to do */
            }
            if (true === $object) {
                $collection = new Collection($collection);
            }
            return $collection;
        }

        public function get($object = false)
        {
            $this->results = empty($this->results) ? $this->fetch() : $this->results;
            return $this->exec($object);
        }

        public function pk()
        {
            return $this->map['pk'];
        }

        public function map()
        {
            $query      = "SHOW COLUMNS FROM $this->database.$this->table";
            $res        = $this->fetch($query);

            if (!count($res)) {
                $sql = "CREATE TABLE $this->database.$this->table (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime NOT NULL
                ) COMMENT='Auto generated table $this->table' ENGINE='InnoDB' COLLATE 'utf8_general_ci';";
                $this->db->query($sql);
                $query      = "SHOW COLUMNS FROM $this->database.$this->table";
                $res        = $this->fetch($query);
            }

            $settings   = isAke(self::$config, "$this->database.$this->table");
            $relations  = isAke($settings, 'relations', false);

            if (false === $relations) {
                $relations      = array();
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

            $fields = $nullable = $keys = $default = array();
            $pk = null;

            if (count($res)) {
                foreach ($res as $row) {
                    $fields[$row['Field']] = array(
                        'type' => typeSql($row['Type'])
                    );
                    $nullable[$row['Field']] = 'yes' == Inflector::lower($row['Null']) ? true : false;
                    $default[$row['Field']] = is_null($row['Default']) ? 'null' : $row['Default'];
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
                'nullable'  => $nullable,
                'default'   => $default,
                'pk'        => $pk,
                'keys'      => $keys
            );

            if (false === $relations) {
                $relations = array();
            }
            if (count($keys)) {
                foreach ($keys as $key) {
                    if (strstr($key, '_id')) {
                        $fkField = repl('_id', '', $key);
                        if (!Arrays::in($fkField, $relations)) {
                            array_push($relations, $fkField);
                        }
                    }
                }
            }
            self::$config["$this->database.$this->table"]['relations'] = $relations;
            return $this;
        }

        public function all($object = false)
        {
            return $this->fetch(null, $object);
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
            $this->fields   = array();
            $this->as       = array();
            $this->joins    = array();
            $this->havings  = array();
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

        public function save(Container $row, $object)
        {
            $this->foreign = $row->foreign();
            $data = $this->clean($row->assoc());

            $id = isAke($data, $this->map['pk'], null);

            if (strlen($id)) {
                unset($data[$this->map['pk']]);
                $row = $this->edit($id, $data, $object);
            } else {
                $row = $this->add($data, $object);
            }
            $this->foreign = null;
            return $row;
        }

        private function clean($data)
        {
            foreach ($data as $key => $value) {
                if (startsWith($key, 'join_')) {
                    unset($data[$key]);
                }
            }
            return $data;
        }

        private function add($data, $object)
        {
            $q = "INSERT INTO $this->database.$this->table SET ";
            $fields = array_keys($this->map['fields']);
            foreach ($data as $k => $v) {

                if (is_callable($v)) {
                    continue;
                }

                $skip = false;

                if (!Arrays::in($k, $fields)) {
                    if (count($this->foreign)) {
                        $skipException = false;
                        foreach ($this->foreign as $originalField => $renamed) {
                            if ($renamed == $k) {
                                list($db, $table, $field) = explode('.', $originalField);
                                if ($db != $this->database || $table != $this->table) {
                                    $skip           = true;
                                    $skipException  = true;
                                    break;
                                } else {
                                    $k              = $field;
                                    $skipException  = true;
                                    break;
                                }
                            }
                        }
                    }
                    if (false === $skipException) {
                        throw new Exception("Field '$k' is unknown in the table '$this->table'.");
                    }
                }

                if (true === $skip) {
                    continue;
                }

                if (!strlen($v)) {
                    $nullable   = $this->map['nullable'][$k];
                    $default    = $this->map['default'][$k];
                    if (true !== $nullable && 'null' === $default) {
                        throw new Exception("Field '$k' must not be nulled in the table '$this->table'.");
                    }
                }
                $q .= "$this->database.$this->table.$k = '" . addslashes($v) . "', ";
            }
            $q = substr($q, 0, -2);

            $insert = $this->db->prepare($q);
            if (false === $update) {
                throw new Exception("The query '$q' is uncorrect. Please check it.");
            }
            $insert->execute();
            $insert->close();

            $data[$this->map['pk']] = $this->db->insert_id;
            return true === $object ? $this->row($data) : $data;
        }

        private function edit($id, $data, $object)
        {
            $idData = isAke($data, $this->map['pk'], null);
            if (!is_null($idData)) {
                unset($data[$this->map['pk']]);
            }
            $old = $this->find($id)->assoc();
            $idData = isAke($old, $this->map['pk'], null);
            if (!is_null($idData)) {
                unset($old[$this->map['pk']]);
            }
            $data   = array_merge($old, $data);
            $fields = array_keys($this->map['fields']);
            $q      = "UPDATE $this->database.$this->table SET ";
            foreach ($data as $k => $v) {
                if (is_callable($v)) {
                    continue;
                }

                $skip = false;

                if (!Arrays::in($k, $fields)) {
                    if (count($this->foreign)) {
                        $skipException = false;
                        foreach ($this->foreign as $originalField => $renamed) {
                            if ($renamed == $k) {
                                list($db, $table, $field) = explode('.', $originalField);
                                if ($db != $this->database || $table != $this->table) {
                                    $skip           = true;
                                    $skipException  = true;
                                    break;
                                } else {
                                    $k              = $field;
                                    $skipException  = true;
                                    break;
                                }
                            }
                        }
                    }
                    if (false === $skipException) {
                        throw new Exception("Field '$k' is unknown in the table '$this->table'.");
                    }
                }

                if (true === $skip) {
                    continue;
                }

                if (!strlen($v)) {
                    $nullable   = $this->map['nullable'][$k];
                    $default    = $this->map['default'][$k];
                    if (true !== $nullable && 'null' === $default) {
                        throw new Exception("Field '$k' must not be nulled in the table '$this->table'.");
                    }
                }

                $q .= "$this->database.$this->table.$k = '" . addslashes($v) . "', ";
            }
            $q = substr($q, 0, -2);
            $q .= " WHERE $this->database.$this->table." . $this->map['pk'] . " = '" . addslashes($id) . "'";

            $update = $this->db->prepare($q);
            if (false === $update) {
                throw new Exception("The query '$q' is uncorrect. Please check it.");
            }
            $update->execute();
            $update->close();

            $data[$this->map['pk']] = $id;
            return true === $object ? $this->row($data) : $data;
        }

        public function deleteRow($id)
        {
            $q = "DELETE FROM $this->database.$this->table WHERE $this->database.$this->table." . $this->map['pk'] . " = '" . addslashes($id) . "'";
            $this->db->query($q);
            return $this;
        }

        public function update(array $updates, $where = null)
        {
            $res = !empty($where) ? $this->where($where)->exec() : $this->all();
            if (count($res)) {
                if (count($updates)) {
                    foreach ($updates as $key => $newValue) {
                        foreach ($res as $row) {
                            $val = isAke($row, $field, null);
                            if ($val != $newValue) {
                                $row[$field] = $newValue;
                                $this->edit($row[$this->map['pk']], $row);
                            }
                        }
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
                    $this->deleteRow($row[$this->map['pk']]);
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
            $this->makeResults();
            $res = $this->results;
            if (true === $object) {
                $row = count($res) ? $this->row(Arrays::first($res)) : null;
            } else {
                $row = count($res) ? Arrays::first($res) : array();
            }
            $this->reset();
            return $row;
        }

        public function only($field, $default = null)
        {
            $sql = strstr($field, ' ') ? true : false;
            if (false === $sql) {
                $row = $this->first(true);
                return $row instanceof Container
                ? !is_string($row->$field)
                    ? $row->$field()
                    : $row->$field
                : $default;
            } else {
                $res = $this->fetch($field);
                $row = count($res) ? Arrays::first($res) : null;
                if (null !== $row) {
                    return Arrays::first($row);
                }
                return $default;
            }
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
                    ? $this->row(
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

        public function last($object = false)
        {
            $this->makeResults();
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
            if (true === $object) {
                $collection = new Collection($collection);
            }
            return $collection;
        }

        public function delete($where = null)
        {
            if (is_null($where)) {
                return $this->exec(true)->delete();
            } else {
                return $this->where($where)->exec(true)->delete();
            }
        }

        public function join(Database $model, $condition = null, $type = 'LEFT')
        {
            array_push($this->joins, array($model, $condition, $type));
            return $this;
        }

        public function leftJoin(Database $model, $condition = null)
        {
            return $this->join($model, $condition, 'LEFT');
        }

        public function rightJoin(Database $model, $condition = null)
        {
            return $this->join($model, $condition, 'RIGHT');
        }

        public function innerJoin(Database $model, $condition = null)
        {
            return $this->join($model, $condition, 'INNER');
        }

        public function outerJoin(Database $model, $condition = null)
        {
            return $this->join($model, $condition, 'OUTER');
        }

        public function field($field)
        {
            $fields = array_keys($this->map['fields']);
            if (!strstr($field, '.')) {
                if (!Arrays::in($field, $fields)) {
                    throw new Exception("The field '$field' does not exist in table '$this->table'.");
                }
                $field = "$this->database.$this->table.$field";
                array_push($this->fields, $field);
            } else {
                $tab = explode('.', $field);
                if (count($tab) == 2) {
                    $model = model(Inflector::lower(Arrays::first($tab)));
                    $field = Inflector::lower(Arrays::last($tab));
                    $database = $model->database;
                    $table = $model->table;
                } elseif (count($tab) == 3) {
                    $database = Inflector::lower(Arrays::first($tab));
                    $table = Inflector::lower($tab[1]);
                    $field = Inflector::lower(Arrays::last($tab));
                }
                if (isset($database) && isset($table) && isset($field)) {
                    $field = "$database.$table.$field";
                    array_push($this->fields, $field);
                }
            }
            return $this;
        }

        public function named($name)
        {
            if (count($this->fields)) {
                $field = Arrays::last($this->fields);
                $this->as[$field] = $name;
            }
            return $this;
        }

        public function fields($fields)
        {
            if (is_string($fields)) {
                $fields = repl(' ', '', $fields);
                if (strstr($fields, ',')) {
                    $fields = explode(',', $fields);
                } else {
                    $fields = array($fields);
                }
            }
            if (!Arrays::is($fields)) {
                throw new Exception("You must provide an array as first argument.");
            }
            foreach ($fields as $field) {
                $this->field($field);
            }
            return $this;
        }

        private function makeResults()
        {
            if (count($this->fields)) {
                $pk = "$this->database.$this->table." . $this->pk();
                $hasPk = false;
                $select = '';
                foreach ($this->fields as $field) {
                    if (false === $hasPk) {
                        $hasPk = $field == $pk;
                    }
                    list($db, $table, $tmpField) = explode('.', $field, 3);
                    $as = isAke($this->as, $field, null);
                    if ($db == $this->database && $table == $this->table) {
                        if (is_null($as)) {
                            $select .= "$field, ";
                        } else {
                            $select .= "$field AS $as, ";
                        }
                    } else {
                        if (is_null($as)) {
                            $select .= "$field AS " . 'join_' . str_replace('.', '_', Inflector::lower($field)) . ", ";
                        } else {
                            $select .= "$field AS $as, ";
                        }
                    }
                }
                if (false === $hasPk) {
                    $select .= "$pk, ";
                }
            } else {
                $fields = array_keys($this->map['fields']);
                $select = '';
                foreach ($fields as $field) {
                    $select .= "$this->database.$this->table.$field, ";
                }
            }

            $select = substr($select, 0, -2);

            if (!count($this->joins)) {
                $query = "SELECT $select FROM $this->database.$this->table WHERE ";
            } else {
                $query = "SELECT $select FROM $this->database.$this->table ";
                foreach ($this->joins as $join) {
                    list($model, $condition, $type) = $join;
                    $joinKey = $model->table . '_id';
                    $fpk = $model->pk();
                    if (is_null($condition)) {
                        $query .= "$type JOIN $model->database.$model->table ON $this->database.$this->table.$joinKey = $model->database.$model->table.$fpk\n";
                    } else {
                        $query .= "$type JOIN $model->database.$model->table ON $condition\n";
                    }
                }
                $query .= "WHERE\n";
            }
            if (count($this->wheres)) {
                $first = true;
                foreach ($this->wheres as $where) {
                    list($op, $condition) = $where;
                    if (count($this->joins)) {
                        $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                        $condition  = repl('NOT IN', 'NOTIN', $condition);
                        list($field, $operator, $value) = explode(' ', $condition, 3);
                        if (!strstr($field, '.')) {
                            $field = "$this->database.$this->table.$field";
                        }
                        $condition = "$field $operator $value";
                        $condition  = repl('NOTLIKE', 'NOT LIKE', $condition);
                        $condition  = repl('NOTIN', 'NOT IN', $condition);
                    }
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
                        if (!strstr($groupBy, '.')) {
                            $query .= ", $this->database.$this->table.$groupBy";
                        } else {
                            $query .= ", $groupBy";
                        }
                    } else {
                        if (!strstr($groupBy, '.')) {
                            $query .= "$this->database.$this->table.$groupBy";
                        } else {
                            $query .= $groupBy;
                        }
                    }
                    $first = false;
                }
            }
            if (count($this->havings)) {
                $sql = array();
                foreach ($query->havings as $having) {
                    $sql[] = 'AND '. $having['column'] . ' ' . $having['operator'] . ' ' . $having['value'];
                }

                $query .= 'HAVING ' . preg_replace('/AND /', '', implode(' ', $sql), 1);
            }

            if (count($this->orders)) {
                $query .= ' ORDER BY ';
                $first = true;
                foreach ($this->orders as $order) {
                    list($field, $direction) = $order;
                    if (false === $first) {
                        if (!strstr($field, '.')) {
                            $query .= ", $this->database.$this->table.$field $direction";
                        } else {
                            $query .= ", $field $direction";
                        }
                    } else {
                        if (!strstr($field, '.')) {
                            $query .= "$this->database.$this->table.$field $direction";
                        } else {
                            $query .= "$field $direction";
                        }
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

        public function take($limit, $offset = 0)
        {
            return $this->limit($limit, $offset);
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

        public function between($field, $min, $max, $object = false)
        {
            return $this->where($field . ' >= ' . $min)->where($field . ' <= ' . $max)->exec($object);
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
                    $db         = Database::instance($db, $table, $host, $username, $password);
                    $share      = function () use ($obj, $callable) {
                        $args   = func_get_args();
                        $args[] = $obj;
                        $args[] = $db;
                        return call_user_func_array($callable , $args);
                    };
                    $obj->event($name, $share);
                }
            };

            $save = function ($object = true) use ($obj, $params) {
                list($db, $table, $host, $username, $password) = $params;
                $db = Database::instance($db, $table, $host, $username, $password);
                return $db->save($obj, $object);
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
                return $db->deleteRow($obj->$pk);
            };

            $id = function () use ($obj, $params) {
                list($db, $table, $host, $username, $password) = $params;
                $db = Database::instance($db, $table, $host, $username, $password);
                $pk = $db->pk();
                return $obj->$pk;
            };

            $exists = function () use ($obj, $params) {
                list($db, $table, $host, $username, $password) = $params;
                $db = Database::instance($db, $table, $host, $username, $password);
                $pk = $db->pk();
                return isset($obj->$pk);
            };

            $touch = function () use ($obj) {
                if (!isset($obj->created_at))  $obj->created_at = time();
                $obj->updated_at = time();
                return $obj;
            };

            $duplicate = function () use ($obj, $params) {
                list($db, $table, $host, $username, $password) = $params;
                $db = Database::instance($db, $table, $host, $username, $password);
                $pk = $db->pk();
                if (isset($obj->$pk)) unset($obj->$pk);
                if (isset($obj->created_at)) unset($obj->created_at);
                return $obj->save();
            };

            $as = $this->as;

            $foreign = function () use ($as) {
                return $as;
            };

            $obj->event('save', $save)
            ->event('delete', $delete)
            ->event('exists', $exists)
            ->event('id', $id)
            ->event('touch', $touch)
            ->event('duplicate', $duplicate)
            ->event('foreign', $foreign)
            ->event('extend', $extend);

            list($db, $table, $host, $username, $password) = $params;
            $settings   = isAke(self::$config, "$db.$table");
            $functions  = isAke($settings, 'functions');

            if (count($functions)) {
                foreach ($functions as $closureName => $callable) {
                    $closureName    = lcfirst(Inflector::camelize($closureName));
                    $share          = function () use ($obj, $params, $callable) {
                        list($db, $table, $host, $username, $password) = $params;
                        $args       = func_get_args();
                        $args[]     = $obj;
                        $args[]     = Database::instance($db, $table, $host, $username, $password);
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
                    if (is_string($field)) {
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
                                $obj->$field = $fkObject->id();
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
