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
        $orders     = array(),
        $cache      = false;

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
            $fields = array_keys($this->map['fields']);

            $method = substr($fn, 0, 2);
            $object = lcfirst(substr($fn, 2));

            if ('is' === $method && strlen($fn) > 2) {
                $field = Inflector::uncamelize($object);

                if (!Arrays::in($field, $fields)) {
                    $field = $field . '_id';
                    $model = Arrays::first($args);

                    if ($model instanceof Container) {
                        $idFk = $model->id();
                    } else {
                        $idFk = $model;
                    }

                    return $this->where("$field = $idFk");
                } else {
                    return $this->where($field . ' = ' . Arrays::first($args));
                }
            }

            $method = substr($fn, 0, 3);
            $object = lcfirst(substr($fn, 3));

            if (strlen($fn) > 3) {
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
            }

            $method = substr($fn, 0, 4);
            $object = lcfirst(substr($fn, 4));

            if ('orIs' === $method && strlen($fn) > 4) {
                $field = Inflector::uncamelize($object);

                if (!Arrays::in($field, $fields)) {
                    $field = $field . '_id';
                    $model = Arrays::first($args);

                    if ($model instanceof Container) {
                        $idFk = $model->id();
                    } else {
                        $idFk = $model;
                    }

                    return $this->where("$field = $idFk", 'OR');
                } else {
                    return $this->where($field . ' = ' . Arrays::first($args), 'OR');
                }
            } elseif('like' === $method && strlen($fn) > 4) {
                $field = Inflector::uncamelize($object);
                $op = count($args) == 2 ? Arrays::last($args) : 'AND';

                return $this->like($field, Arrays::first($args), $op);
            }

            $method = substr($fn, 0, 5);
            $object = lcfirst(substr($fn, 5));

            if (strlen($fn) > 5) {
                if ('where' == $method) {
                    $field = Inflector::uncamelize($object);

                    if (!Arrays::in($field, $fields)) {
                        $field = $field . '_id';
                        $model = Arrays::first($args);

                        if ($model instanceof Container) {
                            $idFk = $model->id();
                        } else {
                            $idFk = $model;
                        }

                        return $this->where("$field = $idFk");
                    } else {
                        return $this->where($field . ' ' . Arrays::first($args));
                    }
                } elseif ('xorIs' === $method) {
                    $field = Inflector::uncamelize($object);

                    if (!Arrays::in($field, $fields)) {
                        $field = $field . '_id';
                        $model = Arrays::first($args);

                        if ($model instanceof Container) {
                            $idFk = $model->id();
                        } else {
                            $idFk = $model;
                        }

                        return $this->where("$field = $idFk", 'XOR');
                    } else {
                        return $this->where($field . ' = ' . Arrays::first($args), 'XOR');
                    }
                } elseif ('andIs' === $method) {
                    $field = Inflector::uncamelize($object);

                    if (!Arrays::in($field, $fields)) {
                        $field = $field . '_id';
                        $model = Arrays::first($args);

                        if ($model instanceof Container) {
                            $idFk = $model->id();
                        } else {
                            $idFk = $model;
                        }

                        return $this->where("$field = $idFk");
                    } else {
                        return $this->where($field . ' = ' . Arrays::first($args));
                    }
                }
            }

            $method = substr($fn, 0, 6);
            $object = Inflector::uncamelize(lcfirst(substr($fn, 6)));

            if (strlen($fn) > 6) {
                if ('findBy' == $method) {
                    return $this->findBy($object, Arrays::first($args));
                }
            }


            $method = substr($fn, 0, 7);
            $object = lcfirst(substr($fn, 7));

            if (strlen($fn) > 7) {
                if ('orWhere' == $method) {
                    $field = Inflector::uncamelize($object);

                    if (!Arrays::in($field, $fields)) {
                        $field = $field . '_id';
                        $model = Arrays::first($args);

                        if ($model instanceof Container) {
                            $idFk = $model->id();
                        } else {
                            $idFk = $model;
                        }

                        return $this->where("$field = $idFk", 'OR');
                    } else {
                        return $this->where($field . ' ' . Arrays::first($args), 'OR');
                    }
                } elseif ('orderBy' == $method) {
                    $object = Inflector::uncamelize(lcfirst(substr($fn, 7)));

                    if ($object == 'id') {
                        $object = $this->pk();
                    }

                    if (!Arrays::in($object, $fields)) {
                        $object = Arrays::in($object . '_id', $fields) ? $object . '_id' : $object;
                    }

                    $direction = (count($args)) ? Arrays::first($args) : 'ASC';

                    return $this->order($object, $direction);
                } elseif ('groupBy' == $method) {
                    $object = Inflector::uncamelize(lcfirst(substr($fn, 7)));

                    if ($object == 'id') {
                        $object = $this->pk();
                    }

                    if (!Arrays::in($object, $fields)) {
                        $object = Arrays::in($object . '_id', $fields) ? $object . '_id' : $object;
                    }

                    return $this->groupBy($object);
                }
            }

            $method = substr($fn, 0, 9);
            $object = Inflector::uncamelize(lcfirst(substr($fn, 9)));

            if (strlen($fn) > 9) {
                if ('findOneBy' == $method) {
                    return $this->findOneBy($object, Arrays::first($args));
                }
            }

            $method = substr($fn, 0, 13);
            $object = Inflector::uncamelize(lcfirst(substr($fn, 13)));

            if (strlen($fn) > 13) {
                if ('findObjectsBy' == $method) {
                    return $this->findBy($object, Arrays::first($args), true);
                }
            }

            $method = substr($fn, 0, 15);
            $object = Inflector::uncamelize(lcfirst(substr($fn, 15)));

            if (strlen($fn) > 15) {
                if ('findOneObjectBy' == $method) {
                    return $this->findOneBy($object, Arrays::first($args), true);
                }
            }

            $method = substr($fn, 0, 8);
            $object = lcfirst(substr($fn, 8));

            if (strlen($fn) > 8) {
                if ('xorWhere' == $method) {
                    $field = Inflector::uncamelize($object);

                    if (!Arrays::in($field, $fields)) {
                        $field = $field . '_id';
                        $model = Arrays::first($args);

                        if ($model instanceof Container) {
                            $idFk = $model->id();
                        } else {
                            $idFk = $model;
                        }

                        return $this->where("$field = $idFk", 'XOR');
                    } else {
                        return $this->where($field . ' ' . Arrays::first($args), 'XOR');
                    }
                } elseif('andWhere' == $method) {
                    $field = Inflector::uncamelize($object);

                    if (!Arrays::in($field, $fields)) {
                        $field = $field . '_id';
                        $model = Arrays::first($args);

                        if ($model instanceof Container) {
                            $idFk = $model->id();
                        } else {
                            $idFk = $model;
                        }

                        return $this->where("$field = $idFk");
                    } else {
                        return $this->where($field . ' ' . Arrays::first($args));
                    }
                }
            } else {
                $field = $fn;
                $fieldFk = $fn . '_id';
                $op = count($args) == 2 ? Inflector::upper(Arrays::last($args)) : 'AND';

                if (Arrays::in($field, $fields)) {
                    return $this->where($field . ' = ' . Arrays::first($args), $op);
                } else if (Arrays::in($fieldFk, $fields)) {
                    $model = Arrays::first($args);

                    if ($model instanceof Container) {
                        $idFk = $model->id();
                    } else {
                        $idFk = $model;
                    }

                    return $this->where("$fieldFk = $idFk", $op);
                }
            }

            throw new Exception("Method '$fn' is unknown.");
        }

        public function __destruct()
        {
            $this->db->close();
        }

        private function foreign($model, Container $row, $many = false, $object = false)
        {
            if (strstr($model, '.')) {
                list($db, $table) = explode('.', $model, 2);
                $db = model($table, $db);
            } else {
                $db = model($model);
            }
            $pk = $db->pk();
            $field = $db->table . '_id';

            return true === $many ? $db->where($pk . ' = ' . $row->$field)->exec($object) : $db->where($pk . ' = ' . $row->$field)->first($object);
        }

        public function manyToMany($model, Container $row, $object = false)
        {
            return $this->foreign($model, $row, true, $object);
        }

        public function manyToOne($model, Container $row, $object = false)
        {
            return $this->foreign($model, $row, false, $object);
        }

        public function oneToMany($model, Container $row, $object = false)
        {
            return $this->foreign($model, $row, true, $object);
        }

        public function oneToOne($model, Container $row, $object = false)
        {
            return $this->foreign($model, $row, false, $object);
        }

        public static function instance($db, $table, $host = 'localhost', $username = 'root', $password = '')
        {
            $key    = sha1(serialize(func_get_args()));
            $has    = Instance::has('Database', $key);
            if (true === $has) {
                return Instance::get('Database', $key);
            } else {
                return Instance::make('Database', $key, new self($db, $table, $host, $username, $password));
            }
        }

        public function db()
        {
            return $this->db;
        }

        public function query($query)
        {
            $start  = $this->getTime();
            $res    = $this->db->query($query);
            $this->incQueries($start);
            return $res;
        }

        private function intersect($tab1, $tab2)
        {
            $ids1       = array();
            $ids2       = array();
            $collection = array();

            foreach ($tab1 as $row) {
                $id = isAke($row, $this->pk(), null);
                if (strlen($id)) {
                    array_push($ids1, $id);
                }
            }

            foreach ($tab2 as $row) {
                $id = isAke($row, $this->pk(), null);
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

        public function trick(Closure $condition, $op = 'AND', $results = array())
        {
            $data = !count($results) ? $this->fetch() : $results;
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

        public function fetch($query = null, $object = false)
        {
            $start = $this->getTime();

            $query = is_null($query)
            ? "SELECT $this->database.$this->table.* FROM $this->database.$this->table"
            : $query;

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

            $this->incQueries($start);

            return $collection;
        }

        public function rows($object = false)
        {
            return $this->get($object, false);
        }

        public function get($object = false, $exec = true)
        {
            $this->results = empty($this->results) ? $this->fetch() : $this->results;

            return true === $exec ? $this->exec($object) : $this;
        }

        public function pk()
        {
            if (strlen($this->map['pk'])) {
                return $this->map['pk'];
            } else {
                return $this->table . '_id';
            }
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

        public function full()
        {
            return $this->get(false, false);
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
            $this->cache    = false;

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

            $id = isAke($data, $this->pk(), null);
            unset($data[$this->pk()]);

            if (strlen($id)) {
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
                if ($v instanceof Closure) {
                    continue;
                }

                $skip = false;
                $skipException = false;

                if (!Arrays::in($k, $fields)) {
                    if (count($this->foreign)) {
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

                    if (true !== $nullable && 'null' === $default && $k != $this->pk()) {
                        throw new Exception("Field '$k' must not be nulled in the table '$this->table'.");
                    }
                }
                $q .= "$this->database.$this->table.$k = '" . addslashes($v) . "', ";
            }

            $q = substr($q, 0, -2);

            $insert = $this->db->prepare($q);

            if (false === $insert) {
                throw new Exception("The query '$q' is uncorrect. Please check it.");
            }

            $insert->execute();
            $insert->close();

            $data[$this->pk()] = $this->db->insert_id;

            return true === $object ? $this->row($data) : $data;
        }

        private function edit($id, $data, $object)
        {
            $idData = isAke($data, $this->pk(), null);

            if (!is_null($idData)) {
                unset($data[$this->pk()]);
            }

            $old = $this->find($id)->assoc();
            $idData = isAke($old, $this->pk(), null);

            if (!is_null($idData)) {
                unset($old[$this->pk()]);
            }

            $data   = array_merge($old, $data);
            $fields = array_keys($this->map['fields']);
            $q      = "UPDATE $this->database.$this->table SET ";

            foreach ($data as $k => $v) {
                if ($v instanceof Closure) {
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
            $q .= " WHERE $this->database.$this->table." . $this->pk() . " = '" . addslashes($id) . "'";

            $update = $this->db->prepare($q);

            if (false === $update) {
                throw new Exception("The query '$q' is uncorrect. Please check it.");
            }

            $update->execute();
            $update->close();

            $data[$this->pk()] = $id;

            return true === $object ? $this->row($data) : $data;
        }

        public function deleteRow($id)
        {
            $q = "DELETE FROM $this->database.$this->table WHERE $this->database.$this->table." . $this->pk() . " = '" . addslashes($id) . "'";
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
                                $this->edit($row[$this->pk()], $row);
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
                    $this->deleteRow($row[$this->pk()]);
                }
            }

            return $this;
        }

        public function find($id, $object = true)
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

            $q = "SELECT $select FROM $this->database.$this->table WHERE $this->database.$this->table." . $this->pk() . " = '" . addslashes($id) . "'";

            $res = $this->fetch($q);

            if (count($res)) {
                $row = Arrays::first($res);

                return $object ? $this->row($row) : $row;
            }

            return $object ? null : array();
        }

        public function findOneBy($field, $value, $object = false)
        {
            return $this->findBy($field, $value, true, $object);
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

        public function object()
        {
            return $this->first(true);
        }

        public function one($object = true)
        {
            return $this->first($object);
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

        public function execute($object = false, $results = null)
        {
            return $this->exec($object, $results);
        }

        public function objects($results = null)
        {
            return $this->exec(true, $results);
        }

        public function toArray($results = null)
        {
            return $this->exec(false, $results);
        }

        public function run($object = false)
        {
            return $this->exec($object, $this->results);
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

        public function with($model)
        {
            return $this->join($model);
        }

        public function join($model, $condition = null, $type = 'LEFT')
        {
            $model = is_string($model) ? model($model) : $model;
            array_push($this->joins, array($model, $condition, $type));

            return $this;
        }

        public function leftJoin($model, $condition = null)
        {
            return $this->join($model, $condition, 'LEFT');
        }

        public function rightJoin($model, $condition = null)
        {
            return $this->join($model, $condition, 'RIGHT');
        }

        public function innerJoin($model, $condition = null)
        {
            return $this->join($model, $condition, 'INNER');
        }

        public function outerJoin($model, $condition = null)
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

        private function makeResults($fetch = true)
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

                        if ($value instanceof Container) {
                            $value = $value->id();
                            $field = $field . '_id';
                        }

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

            $query = str_replace('WHERE 1 = 1', '', $query);

            $this->query = $query;

            if (true === $fetch) {
                if (true === $this->cache) {
                    $cache = coreCache();
                    $key = sha1($query) . '::dataQuery';
                    $cached = $cache->get($key);

                    if (!strlen($cached)) {
                        $cached = $this->fetch($query);
                        $cache->set($key, serialize($cached));
                        $cache->expire($key, Config::get('database.cache.ttl', 7200));
                    } else {
                        $cached = unserialize($cached);
                    }

                    $this->results = $cached;
                } else {
                    $this->results = $this->fetch($query);
                }
            } else {
                return $query;
            }

            return $this;
        }

        public function getQuery()
        {
            return $this->makeResults(false);
        }

        public function isNull($field, $op = 'AND')
        {
            return $this->where("$field IS NULL", $op);
        }

        public function isNotNull($field, $op = 'AND')
        {
            return $this->where("$field IS NOT NULL", $op);
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

        public function remember($minutes = 60)
        {
            return $this->cache($minutes * 60);
        }

        public function cache($bool = true)
        {
            /* Polymorphism */
            if (is_int($bool)) {
                if ($bool == 0) {
                    $bool = false;
                } elseif ($bool == 1) {
                    $bool = true;
                } elseif (60 <= $bool) {
                    Config::set('database.cache.ttl', $bool);
                } else {
                    $bool = true;
                }
            }

            $this->cache = $bool;

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
            /* polymorphism */
            if (is_string($condition)) {
                $this->wheres[] = array($op, $condition);
            } elseif (Arrays::is($condition)) {
                if (Arrays::isAssoc($condition)) {
                    foreach ($consition as $key => $value) {
                        $this->wheres[] = array($op, "$key = $value");
                    }
                }
            }

            return $this;
        }

        public function like($field, $str, $op = 'AND')
        {
            return $this->where("$field LIKE '%" . addslashes($str) . "%'", $op);
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

        public function firstOrNew($tab = array())
        {
            return $this->firstOrCreate($tab, false);
        }

        public function firstOrCreate($tab = array(), $save = true)
        {
            if (count($tab)) {
                foreach ($tab as $key => $value) {
                    $this->where("$key = '" . addslashes($value) . "'");
                }

                $first = $this->first(true);

                if (!is_null($first)) {
                    return $first;
                }
            }

            $item = $this->create($tab);

            return !$save ? $item : $item->save();
        }

        public function replace($compare = array(), $update = array())
        {
            $instance = $this->firstOrCreate($compare);

            return $instance->hydrate($update)->save();
        }

        /**
         * Get the first model for the given attributes.
         *
         * @param  array  $attributes
         * @param  bool  $object
         * @return \Database
         */
        public function firstByAttributes(array $attributes, $object = false)
        {
            return $this->where($attributes)->first($object);
        }

        public function findOrNew($id, $object = true)
        {
            if (!is_null($item = $this->find($id, $object))) {
                return $item;
            }

            return $this->create();
        }

        public function findOrFail($id, $object = true)
        {
            if (!is_null($item = $this->find($id, $object))) {
                return $item;
            }

            throw new Exception("Row '$id' in '$this->table' is unknown.");
        }

        public function destroy($ids)
        {
            /* polymorphism */
            $ids = Arrays::is($ids) ? $ids : func_get_args();

            // We will actually pull the models from the database table and call delete on
            // each of them individually so that their events get fired properly with a
            // correct set of attributes in case the developers wants to check these.
            $key    = $this->pk();
            $rows   = $this->in($key, $ids)->execute(true);
            $count  = $rows->count();
            if (0 < $count) {
                $rows->delete();
            }

            // We return the total number of deletes
            // for the operation. The developers can then check this number as a boolean
            // type value or get this total count of records deleted for logging, etc.
            return $count;
        }

        public function create($tab = array())
        {
            return $this->row($tab);
        }

        public function row($tab = array())
        {
            $fields = array_keys($this->map['fields']);
            $pk     = $this->pk();
            $id     = isAke($tab, $pk, false);

            if (count($tab)) {
                foreach ($tab as $key => $value) {
                    if (!Arrays::in($key, $fields)) {
                        unset($tab[$key]);
                    }
                }
                foreach ($fields as $field) {
                    $val = isAke($tab, $field, false);
                    if (false === $val && false === $id) {
                        $tab[$field] = null;
                    }
                }
            } else {
                foreach ($fields as $field) {
                    if (false === $id) {
                        $tab[$field] = null;
                    }
                }
            }

            $o = new Container;
            $o->populate($tab);

            return $this->closures($o);
        }

        private function closures($obj)
        {
            $params = $this->args;

            $fn = function ($name, Closure $callable) use ($obj, $params) {
                if (is_callable($callable)) {
                    list($db, $table, $host, $username, $password) = $params;
                    $dbi        = Database::instance($db, $table, $host, $username, $password);
                    $share      = function () use ($obj, $callable, $dbi) {
                        $args   = func_get_args();
                        $args[] = $obj;
                        $args[] = $dbi;
                        return call_user_func_array($callable, $args);
                    };

                    $obj->event($name, $share);

                    return $obj;
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

            $duplicate = function ($object = true) use ($obj, $params) {
                list($db, $table, $host, $username, $password) = $params;
                $db = Database::instance($db, $table, $host, $username, $password);
                $pk = $db->pk();

                if (isset($obj->$pk)) unset($obj->$pk);
                if (isset($obj->created_at)) unset($obj->created_at);

                return $obj->save($object);
            };

            $orm = function () use ($params) {
                list($db, $table, $host, $username, $password) = $params;

                return Database::instance($db, $table, $host, $username, $password);
            };

            $cache = function ($bool = true) use ($params) {
                list($db, $table, $host, $username, $password) = $params;
                $database = Database::instance($db, $table, $host, $username, $password);
                $database->cache($bool);

                return $database;
            };

            $as = $this->as;

            $foreign = function () use ($as) {
                return $as;
            };

            $many = function ($table, $field = null, $object = false) use ($obj) {
                $tab = $obj->assoc();
                $db = model($table);
                $pk = is_null($field) ? $db->pk() : $field;
                $value = $tab[$db->table . '_id'];

                return $db->where("$pk = $value")->execute($object);
            };

            $one = function ($table, $field = null, $object = false) use ($obj) {
                $tab = $obj->assoc();
                $db = model($table);
                $pk = is_null($field) ? $db->pk() : $field;
                $value = $tab[$db->table . '_id'];

                return $db->where("$pk = $value")->first($object);
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

            $obj->event('save', $save)
            ->event('delete', $delete)
            ->event('exists', $exists)
            ->event('id', $id)
            ->event('touch', $touch)
            ->event('duplicate', $duplicate)
            ->event('foreign', $foreign)
            ->event('orm', $orm)
            ->event('many', $many)
            ->event('hydrate', $hydrate)
            ->event('one', $one)
            ->event('cache', $cache)
            ->event('fn', $fn);

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
                            $fks = $fk . 's';
                            $cb = function ($object = true) use ($value, $tableFk, $params) {
                                list($database, $table, $host, $username, $password) = $params;
                                $db = Database::instance($database, $tableFk, $host, $username, $password);

                                if ($db) {
                                    return $db->find($value, $object);
                                }

                                return null;
                            };

                            $obj->event($fk, $cb);

                            $cb = function ($object = true) use ($value, $tableFk, $params) {
                                list($database, $table, $host, $username, $password) = $params;
                                $db = Database::instance($database, $tableFk, $host, $username, $password);

                                if ($db) {
                                    return $db->where($db->pk() . " = '" . addslashes($value) . "'")->exec($object);
                                }

                                return null;
                            };
                            $obj->event($fks, $cb);

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

        private function incQueries($start)
        {
            $numberOfQueries = container()->getNumberOfQueries();
            $numberOfQueries = is_null($numberOfQueries) ? 0 : $numberOfQueries;
            $numberOfQueries++;
            $totalDuration = container()->getTotalDuration();
            $totalDuration = is_null($totalDuration) ? 0 : $totalDuration;
            $totalDuration += $this->getTime() - $start;

            Utils::set('NbQueries', $numberOfQueries);
            Utils::set('SQLTotalDuration', $totalDuration);
        }

        private function getTime()
        {
            $time = microtime();
            $time = explode(' ', $time, 2);

            return (Arrays::last($time) + Arrays::first($time));
        }

        public function extend($name, $callable)
        {
            $key = $this->database . '.' . $this->table;

            $settings   = isAke(self::$config, $key);
            $functions  = isAke($settings, 'functions');

            $functions[$name] = $callable;

            self::$config[$key]['functions'] = $functions;

            return $this;
        }

        public static function cleanCache()
        {
            $cache = coreCache();
            $keys = $cache->keys('*::dataQuery');

            if (count($keys)) {
                foreach ($keys as $key) {
                    $cache->del($key);
                }
            }
        }

        public function getObservableEvents()
        {
            return array_merge(
                array(
                    'creating', 'created', 'updating', 'updated',
                    'deleting', 'deleted', 'saving', 'saved',
                    'restoring', 'restored', 'commiting', 'commited'
                ),
                get_class_methods(__CLASS__)
            );
        }

        public function listenEvent($name, Closure $event)
        {
            $key = "database::$this->database::$this->table::$name";
            events()->listen($key, $event);

            return $this;
        }

        public function fireEvent($event, $halt = true)
        {
            $key = "database::$this->database::$this->table::$event";
            $method = $halt ? 'until' : 'fire';

            return events()->$method($key, $this);
        }

        public function forgetEvent($event, $halt = true)
        {
            $key = "database::$this->database::$this->table::$event";
            events()->forget($key);

            return $this;
        }

        public function flushEvents()
        {
            foreach ($instance->getObservableEvents() as $event) {
                $key = "database::$this->database::$this->table::$event";
                events()->forget($key);
            }

            return $this;
        }
    }
