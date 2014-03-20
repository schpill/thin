<?php
    namespace Thin;
    use PDO;
    use Closure;

    class Activerecord
    {
        public $_keyConnexion;
        public $_model;
        public $_entity;
        public $_cache          = false;
        public $_tts            = 3600;
        public $_count          = 0;
        public $_table;
        public $_relationships  = array();
        public $_pks            = array();
        public $_keys           = array();
        public $_fields         = array();
        public $_settings       = array();
        public $_closures       = array();
        public $_wheres         = array();
        public $_query          = array();

        public function __construct(array $model)
        {
            $this->_model = $model;

            if (Arrays::is($model)) {
                if (Arrays::isAssoc($model)) {
                    $this->_settings = isAke($model, 'settings');
                    if (empty($this->_settings)) {
                        throw new Exception("Settings do not exist.");
                    }
                    if (Arrays::exists('entity', $this->_settings)) {
                        $this->_entity = $this->_settings['entity'];
                    } else {
                        throw new Exception('The model is misconfigured. Entity missing.');
                    }
                    if (Arrays::exists('table', $this->_settings)) {
                        $this->_table = $this->_settings['table'];
                    } else {
                        throw new Exception('The model is misconfigured. Table missing.');
                    }
                    $configs        = container()->getConfig()->getDb();
                    $config         = isAke($configs, $this->_entity);
                    if (empty($config)) {
                        throw new Exception("Database connection settings do not exist.");
                    }

                    $adapter    = $config->getAdapter();
                    $username   = $config->getUsername();
                    $password   = $config->getPassword();
                    $dbName     = $config->getDatabase();
                    $host       = $config->getHost();
                    $dsn        = "$adapter:dbname=$dbName;host=$host";

                    $this->_keyConnexion = sha1(serialize(array($dsn, $username, $password)));
                    $dbs = container()->getArConnexions();
                    if (empty($all)) {
                        $dbs = array();
                    }
                    $db = $dbs[$this->_keyConnexion] = new PDO($dsn, $username, $password);
                    container()->setArConnexions($dbs);

                    $this->_relationships = isAke($this->_settings, 'relationships');
                    $this->_fields = isAke($this->_settings, 'fields');
                    if (empty($this->_fields)) {
                        $this->map();
                    }
                } else {
                    throw new Exception('The model is misconfigured.');
                }
            } else {
                throw new Exception('The model is misconfigured.');
            }
        }

        public function tts($sec = 3600)
        {
            $this->_tts = $sec;
            return $this;
        }

        public function cache($bool)
        {
            $this->_cache = $bool;
            return $this;
        }

        public function pk()
        {
            if (count($this->_pks)) {
                return Arrays::first($this->_pks);
            }
            return $this->_table . '_id';
        }

        public function fk($key)
        {
            if (count($this->_relationships)) {
                foreach ($this->_relationships as $ffk => $infos) {
                    if ($key == $infos['relationKey']) {
                        return $infos;
                    }
                }
            }
            return null;
        }

        public function hasFk($key)
        {
            if (count($this->_relationships)) {
                foreach ($this->_relationships as $ffk => $infos) {
                    if ($key == $infos['fieldName']) {
                        return $infos;
                    }
                }
            }
            return false;
        }

        private function map()
        {
            $q = "SHOW COLUMNS FROM $this->_table";
            $res = $this->query($q, false);
            if (empty($res)) {
                throw new Exception("The system cannot access to the table $this->_table on $this->_entity.");
            }
            $conf = array();
            foreach ($res as $data) {
                $field = $data['Field'];
                $conf[$field] = array();
                $conf[$field]['type'] = typeSql($data['Type']);
                $conf[$field]['nullable'] = ('yes' == Inflector::lower($data['Null'])) ? true : false;
                if ($data['Key'] == 'PRI') {
                    $this->_pks[] = $field;
                }
                if (strlen($data['Key']) && $data['Key'] != 'PRI') {
                    $this->_keys[] = $field;
                }
            }
            $this->_fields = $conf;
        }

        public function fields()
        {
            return array_keys($this->_fields);
        }

        public function findBy($field, $value, $one = false, $recursive = true)
        {
            if ($field == 'id') {
                $field = $this->pk();
            }
            $hasFk = $this->fk($field);
            if (null === $hasFk) {
                $q = "SELECT ". $this->_entity . '.' . $this->_table . '.' . implode(', ' . $this->_entity . '.' . $this->_table . '.' , $this->fields()) . "
                FROM $this->_entity.$this->_table
                WHERE $this->_entity.$this->_table.$field = " . $this->quote($value);
            } else {
                extract($hasFk);
                $value = is_object($value) ? $value->$foreignKey : $value;
                $q = "SELECT ". $this->_entity . '.' . $this->_table . '.' . implode(', ' . $this->_entity . '.' . $this->_table . '.' , $this->fields()) . "
                FROM $this->_entity.$this->_table
                WHERE $this->_entity.$this->_table.$fieldName = " . $this->quote($value);
            }
            $res = $this->query($q, true, $recursive);
            if (true === $one && count($res)) {
                return Arrays::first($res);
            }
            return true === $one ? null : $res;
        }

        public function find($id)
        {
            return $this->findBy($this->pk(), $id, true);
        }

        public function _and($where, $args = array())
        {
            return $this->where($where, $args, 'AND');
        }

        public function _or($where, $args = array())
        {
            return $this->where($where, $args, 'OR');
        }

        public function _xor($where, $args = array())
        {
            return $this->where($where, $args, 'XOR');
        }

        public function where($where, $args = array(), $type = 'AND')
        {
            $q = '';
            if (count($this->_wheres)) {
                $q .= ' ' . $type . ' ';
            }
            if (count($args) && Arrays::isAssoc($args)) {
                foreach ($args as $k => $v) {
                    $where = repl(":$k", !is_int($v) ? $this->quote($v) : $v, $where);
                }
            }
            $q .= $where;
            $this->_wheres[] = $q;
            return $this;
        }

        public function order($field, $direction = 'ASC')
        {
            if (!ake('order', $this->_query)) {
                $this->_query['order'] = array();
            }
            $this->_query['order'][] = array($field, Inflector::upper($direction));
            return $this;
        }

        public function join($join)
        {
            if (!ake('join', $this->_query)) {
                $this->_query['join'] = array();
            }
            $this->_query['join'][] = $join;
            return $this;
        }

        public function limit($limit, $offset = 0)
        {
            $this->_query['limit'] = array($limit, $offset);
            return $this;
        }

        public function groupBy($field)
        {
            $this->_query['groupBy'] = $field;
            return $this;
        }

        public function distinct()
        {
            $this->_query['distinct'] = true;
            return $this;
        }

        public function fetch($obj = true, $chain = false)
        {
            $sql = $this->makeSql();
            return true === $chain ? $this : $this->query($sql, $obj);
        }

        private function makeSql()
        {
            $join = '';
            $distinct = '';
            $groupBy = '';
            $order = '';
            $limit = '';
            $where = (empty($this->_wheres)) ? '1 = 1' : implode('', $this->_wheres);

            if (ake('order', $this->_query)) {
                $order = 'ORDER BY ';
                $i = 0;
                foreach ($this->_query['order'] as $qo) {
                    list($field, $direction) = $qo;
                    if ($i > 0) {
                        $order .= ', ';
                    }
                    $order .= "$this->_entity.$this->_table.$field $direction";
                    $i++;
                }
            }
            if (ake('limit', $this->_query)) {
                list($max, $offset) = $this->_query['limit'];
                $limit = "LIMIT $offset, $max";
            }
            if (ake('join', $this->_query)) {
                $join = implode(' ', $this->_query['join']);
            }
            if (ake('groupBy', $this->_query)) {
                $groupBy = 'GROUP BY ' . $this->_query['groupBy'];
            }
            if (ake('distinct', $this->_query)) {
                $distinct = (true === $this->_query['distinct']) ? 'DISTINCT' : '';
            }
            $sql = "SELECT $distinct " . $this->_entity . '.' . $this->_table . '.' . implode(', ' . $this->_entity . '.' . $this->_table . '.' , $this->fields()) . "
                FROM $this->_entity.$this->_table $join
                WHERE $where $order $limit $groupBy";
            return $sql;
        }

        public function query($sql = null, $obj = true, $recursive = true)
        {
            $db = $this->db();
            $sql = empty($sql) ? $this->makeSql() : $sql;
            $keyCache = sha1($sql . serialize($this->_settings));
            if (true === $this->_cache) {
                $cached = $this->cached($keyCache);
                if (!empty($cached)) {
                    $this->_count = count($cached);
                    return $cached;
                }
            }
            $res = $db->prepare($sql);
            $res->execute();
            $result = array();
            if (false !== $res) {
                $cols = array();
                while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                    $cols[] = $obj ? $this->row($row, $recursive) : $row;
                }
                $result = $cols;
            }
            if (true === $this->_cache) {
                $this->cached($keyCache, $result);
            }
            $this->_count = count($result);
            return $result;
        }

        public function cached($key, $data = null)
        {
            $file = CACHE_PATH . DS . $key . '_sql';
            if (!empty($data)) {
                File::put($file, serialize($data));
                return $data;
            }
            if (File::exists($file)) {
                $age = time() - filemtime($file);
                if ($age > $this->_tts) {
                    File::delete($file);
                } else {
                    return unserialize(fgc($file));
                }
            }
        }

        public function db()
        {
            $dbs = container()->getArConnexions();
            if (null !== $dbs && Arrays::is($dbs)) {
                $db = isAke($dbs, $this->_keyConnexion);
                if (!empty($db)) {
                    return $db;
                }
            }
            return null;
        }

        public function getId()
        {
            $pk = $this->pk();
            return $this->$pk;
        }

        public function setId($id)
        {
            $pk = $this->pk();
            $this->$pk = $id;
            return $this;
        }

        public function describe()
        {
            $desc = array();
            $q = 'DESCRIBE ' . $this->_entity . '.' . $this->_table;
            $res = $this->query($q, false);
            $count = count($res);
            if (0 < $count) {
                $field   = 0;
                $type    = 1;
                $null    = 2;
                $key     = 3;
                $default = 4;
                $extra   = 5;
                $i = 1;
                $p = 1;
                foreach ($res as $row) {
                    list(
                        $length,
                        $scale,
                        $precision,
                        $unsigned,
                        $primary,
                        $index,
                        $primaryPosition,
                        $identity
                    ) = array(
                        null,
                        null,
                        null,
                        null,
                        false,
                        false,
                        null,
                        false
                    );
                    if (preg_match('/unsigned/', $row[$type])) {
                        $unsigned = true;
                    }
                    if (preg_match('/^((?:var)?char)\((\d+)\)/', $row[$type], $matches)) {
                        $row[$type] = $matches[1];
                        $length = $matches[2];
                    } else if (preg_match('/^decimal\((\d+),(\d+)\)/', $row[$type], $matches)) {
                        $row[$type] = 'decimal';
                        $precision = $matches[1];
                        $scale = $matches[2];
                    } else if (preg_match('/^float\((\d+),(\d+)\)/', $row[$type], $matches)) {
                        $row[$type] = 'float';
                        $precision = $matches[1];
                        $scale = $matches[2];
                    } else if (preg_match('/^((?:big|medium|small|tiny)?int)\((\d+)\)/', $row[$type], $matches)) {
                        $row[$type] = $matches[1];
                        // The optional argument of a MySQL int type is not precision
                        // or length; it is only a hint for display width.
                    }
                    if (strlen($row[$key])) {
                        if (Inflector::upper($row[$key]) == 'PRI') {
                            $primary = true;
                            $primaryPosition = $p;
                            if ($row[$extra] == 'auto_increment') {
                                $identity = true;
                                $index = true;
                            } else {
                                $identity = false;
                            }
                            ++$p;
                        } else {
                            $index = true;
                        }
                    }
                    $desc[$this->foldCase($row[$field])] = array(
                        'ENTITY_NAME'      => $this->foldCase($this->_entity),
                        'TABLE_NAME'       => $this->foldCase($this->_table),
                        'COLUMN_NAME'      => $this->foldCase($row[$field]),
                        'COLUMN_POSITION'  => $i,
                        'DATA_TYPE'        => $row[$type],
                        'DEFAULT'          => $row[$default],
                        'NULLABLE'         => (bool) ($row[$null] == 'YES'),
                        'LENGTH'           => $length,
                        'SCALE'            => $scale,
                        'PRECISION'        => $precision,
                        'UNSIGNED'         => $unsigned,
                        'PRIMARY'          => $primary,
                        'PRIMARY_POSITION' => $primaryPosition,
                        'INDEX'            => $index,
                        'IDENTITY'         => $identity
                    );
                    ++$i;
                }
            }
            return $desc;
        }

        public function foldCase($string)
        {
            $value = (string) $string;
            return $value;
        }

        /* méthodes transactionnelles */

        public function begin()
        {
            $db = $this->db();
            $begin = $db->beginTransaction();
            return $this;
        }

        public function inTransaction()
        {
            $db = $this->db();
            return $db->inTransaction();
        }

        public function commit()
        {
            $db = $this->db();
            $commit = $db->commit();
            return $this;
        }

        public function rollback()
        {
            $db = $this->db();
            $rollback = $db->rollBack();
            return $this;
        }

        /* Alias Rollback */
        public function fail()
        {
            return $this->rollback();
        }

        public function closure($name, Closure $closure)
        {
            $this->_closures[$name] = $closure;
            return $this;
        }

        public function row(array $data, $recursive = true, $extends = array())
        {
            if (Arrays::isAssoc($data)) {
                $obj = o(sha1(serialize($data)));
                $obj->db_instance = $this;
                if (count($extends)) {
                    foreach ($extends as $name => $instance) {
                        $closure = function ($object) use ($name, $instance) {
                            $idx = $object->is_thin_object;
                            $objects = Utils::get('thinObjects');
                            return $instance->$name($objects[$idx]);
                        };
                        $obj->_closures[$name] = $closure;
                    }
                }
                $fields = $this->fields();
                foreach ($fields as $field) {
                    $hasFk = $this->hasFk($field);
                    if (false === $hasFk) {
                        $obj->$field = $data[$field];
                    } else {
                        extract($hasFk);
                        $ar = ar($foreignEntity, $foreignTable);
                        $one = contain('toone', Inflector::lower($type));
                        if ($one && $recursive) {
                            $foreignObj = $ar->findBy($foreignKey, $data[$field], $one);
                            $obj->$relationKey = $foreignObj;
                        }
                    }
                }
                $hasFk = ake('relationships', $this->_settings);
                if (true === $hasFk && $recursive) {
                    $rs = $this->_settings['relationships'];
                    if (count($rs)) {
                        foreach ($rs as $field => $infos) {
                            extract($infos);
                            $ar = ar($foreignEntity, $foreignTable);
                            if (!Arrays::in($field, $fields)) {
                                $pk = $this->pk();
                                $obj->$field = $ar->findBy($foreignKey, $obj->$pk, false, false);
                            }
                        }
                    }
                }
                return $obj;
            }
            return null;
        }

        public function delete($row)
        {
            $db = $this->db();
            $pk = $this->pk();
            if (isset($row->$pk)) {
                $q = "DELETE FROM $this->_entity.$this->_table WHERE $this->_entity.$this->_table.$pk = " . $this->quote($row->$pk);
                $res = $db->prepare($q);
                $res->execute();
                return true;
            }
            return false;
        }

        public function save($row)
        {
            $db = $this->db();
            $fields = $this->fields();
            foreach ($fields as $field) {
                $hasFk = $this->hasFk($field);
                if (Arrays::is($hasFk)) {
                    extract($hasFk);
                    $value = $row->$relationKey->$foreignKey;
                    $row->$fieldName = $value;
                }
            }
            $pk = $this->pk();
            $new = !isset($row->$pk);
            $saveFields = $this->fieldsSave();
            if (false === $new) {
                $q = "UPDATE $this->_entity.$this->_table SET ";
                foreach($saveFields as $field) {
                    $value = $row->$field;
                    $q .= "$this->_entity.$this->_table.$field = " . $this->quote($value) . ', ';
                }
                $q = substr($q, 0, -2) . " WHERE $this->_entity.$this->_table.$pk = " . $this->quote($row->$pk);
            } else {
                $q = "INSERT INTO $this->_entity.$this->_table (" . implode(', ', $saveFields) . ") VALUES (";
                foreach($saveFields as $field) {
                    $value = $row->$field;
                    $q .= $this->quote($value) . ', ';
                }
                $q = substr($q, 0, -2) . ')';
            }
            $res = $db->prepare($q);
            $res->execute();
            if (true === $new) {
                $row->$pk = $db->lastInsertId();
            }
            return $row;
        }

        public function fieldsSave()
        {
            $pk = $this->pk();
            $fields = $this->fields();
            $collection = array();
            foreach ($fields as $field) {
                if ($pk != $field) {
                    $collection[] = $field;
                }
            }
            return $collection;
        }

        protected function quote($value, $parameterType = PDO::PARAM_STR)
        {
            if(empty($value)) {
                return "NULL";
            }
            $db = $this->db();
            if (is_string($value)) {
                return $db->quote($value, $parameterType);
            }
            return $value;
        }

        public function between($field, $min, $max)
        {
            return $this->where("$this->_entity.$this->_table.$field BETWEEN $min AND $max")->fetch();
        }

        public function min($field)
        {
            $q = "SELECT MIN($this->_entity.$this->_table.$field) FROM $this->_entity.$this->_table";
            $res = $this->query($q, false);
            $count = count($res);

            if ($count < 1) {
                return $count;
            } else {
                foreach ($res as $row) {
                    $val = Arrays::first($row);
                    return $val;
                }
            }
        }

        public function max($field)
        {
            $q = "SELECT MAX($this->_entity.$this->_table.$field) FROM $this->_entity.$this->_table";
            $res = $this->query($q, false);
            $count = count($res);

            if ($count < 1) {
                return $count;
            } else {
                foreach ($res as $row) {
                    $val = Arrays::first($row);
                    return $val;
                }
            }
        }

        public function avg($field)
        {
            $q = "SELECT AVG($this->_entity.$this->_table.$field) FROM $this->_entity.$this->_table";
            $res = $this->query($q, false);
            $count = count($res);

            if ($count < 1) {
                return $count;
            } else {
                foreach ($res as $row) {
                    $val = Arrays::first($row);
                    return $val;
                }
            }
        }

        public function sum($field)
        {
            $q = "SELECT SUM($this->_entity.$this->_table.$field) FROM $this->_entity.$this->_table";
            $res = $this->query($q, false);
            $count = count($res);

            if ($count < 1) {
                return $count;
            } else {
                foreach ($res as $row) {
                    $val = Arrays::first($row);
                    return $val;
                }
            }
        }

        public function count()
        {
            return (int) $this->_count;
        }

        public function countQuery($field)
        {
            $q = "SELECT COUNT($this->_entity.$this->_table.$field) FROM $this->_entity.$this->_table";
            $res = $this->query($q, false);
            $count = count($res);

            if ($count < 1) {
                return null;
            } else {
                foreach ($res as $row) {
                    $val = Arrays::first($row);
                    return $val;
                }
            }
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
    }
