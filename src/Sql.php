<?php
    namespace Thin;
    use Pdo;
    use Closure;

    class Sql
    {
        public $entity;
        public $type;
        public $fields;
        public $settings;
        public $session;
        public $transactions    = array();
        public $results         = array();
        public $wheres          = array();
        public $db;
        public $transac         = false;
        public $cache           = false;
        public $ttl             = 3600;

        public function __construct($type, $entity = 'db')
        {
            $this->type = $type;
            $this->entity = $entity;

            $this->fields = Arrays::exists($type, Data::$_fields)
            ? Data::$_fields[$type]
            : Data::noConfigFields($type);

            $this->settings = Arrays::exists($type, Data::$_settings)
            ? Data::$_settings[$type]
            : Data::defaultConfig($type);

            $this->session = session(Inflector::camelize('storeSQL_' . $type));
            $data = $this->session->getData();
            if (empty($data)) {
                $data = array();
                $this->session->setData($data);
            }

            $this->db   = $this->connect();
            if (false === $this->checkTable()) {
                $q = 'CREATE TABLE IF NOT EXISTS `datas_' . $this->type . '` (
                  `id` varchar(9) NOT NULL,
                  `datecreate` int(11) unsigned NOT NULL,
                  `datemodify` int(11) unsigned NOT NULL,
                  `value` longtext NOT NULL
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;';
                $this->_query($q);
            }
        }

        private function connect()
        {
            $configs        = container()->getConfig()->getDb();
            $config         = isAke($configs, $this->entity);
            if (empty($config)) {
                throw new Exception("Database configuration does not exist.");
            }
            $username       = $config->getUsername();
            if (empty($username)) {
                throw new Exception("Username is mandatory to connect database.");
            }

            $adapter    = $config->getAdapter();
            $password   = $config->getPassword();
            $dbName     = $config->getDatabase();
            $host       = $config->getHost();
            $dsn        = $config->getDsn();
            if (empty($dsn)) {
                $dsn = "$adapter:dbname=$dbName;host=$host";
            } else {
                $adapter = 'mysql';
            }

            $connexions = Utils::get('SQLConnexions');
            if (null === $connexions) {
                $connexions = array();
            }

            $keyConnexion = sha1(serialize(array($dsn, $username, $password)));
            if (Arrays::exists($keyConnexion, $connexions)) {
                $db = $connexions[$keyConnexion];
            } else {
                switch ($adapter) {
                    case 'mysql':
                        $db = Utils::newInstance('\\PDO', array($dsn, $username, $password));
                        break;
                }
                $connexions[$keyConnexion] = $db;
                Utils::set('SQLConnexions', $connexions);
            }

            return $db;
        }

        public function close()
        {
            $configs        = container()->getConfig()->getDb();
            $config         = isAke($configs, $this->entity);
            if (empty($config)) {
                throw new Exception("Database configuration does not exist.");
            }
            $username       = $config->getUsername();
            if (empty($username)) {
                throw new Exception("Username is mandatory to connect database.");
            }

            $adapter    = $config->getAdapter();
            $password   = $config->getPassword();
            $dbName     = $config->getDatabase();
            $host       = $config->getHost();
            $dsn        = $config->getDsn();
            if (!empty($dsn)) {
                $adapter = 'mysql';
            }

            $connexions = Utils::get('SQLConnexions');
            if (null === $connexions) {
                $connexions = array();
            }

            $keyConnexion = sha1(serialize(array("$adapter:dbname=$dbName;host=$host", $username, $password)));
            if (Arrays::exists($keyConnexion, $connexions)) {
                $connexions[$keyConnexion] = null;
                Utils::set('SQLConnexions', $connexions);
            }
            $this->_isConnected = false;
            return $this;
        }

        private function checkTable()
        {
            $q = "SHOW TABLES";
            $res = $this->_query($q);
            if (Arrays::is($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return false;
            }
            foreach ($res as $row) {
                $table = Arrays::first($row);
                if ($table == 'datas_' . $this->type) {
                    return true;
                }
            }
            return false;
        }

        private function _query($q)
        {
            $result = $this->db->prepare($q);
            $result->execute();
            return $result;
        }

        public function transaction($bool)
        {
            $this->transac = $bool;
            return $this;
        }

        public function cache($bool)
        {
            $this->cache = $bool;
            return $this;
        }

        public function ttl($ttl = 3600)
        {
            $this->ttl = $ttl;
            $this->session->_duration = $ttl;
            return $this;
        }

        public function reset()
        {
            $this->results      = array();
            $this->wheres       = array();
            $this->transactions = array();
            $this->cache        = false;
            $this->transac      = false;
            return $this;
        }

        public function make($data = array())
        {
            $class = $this;
            $obj = new Container;

            $store = function () use ($class, $obj) {
                return $class->save($obj);
            };

            $trash = function () use ($class, $obj) {
                return $class->delete($obj);
            };

            $date = function ($f)  use ($obj) {
                return isset($obj->$f) && is_numeric($obj->$f)
                ? date('Y-m-d H:i:s', $obj->$f)
                : null;
            };

            $hydrate = function ($data)  use ($obj) {
                if (Arrays::isAssoc($data)) {
                    foreach ($data as $k => $v) {
                        $obj->$k = $v;
                    }
                }
                return $obj;
            };

            $display = function ($field)  use ($obj) {
                return isset($obj->$field)
                ? Html\Helper::display($obj->$field)
                : null;
            };

            $tab = function ()  use ($obj) {
                $return = array();
                $fields = $obj->_fields;
                foreach ($fields as $field) {
                    $val = isset($obj->$field)
                    ? $obj->$field
                    : null;
                    $return[$field] = $val;
                }
                return $return;
            };

            $asset = function ($field) use ($obj) {
                return isset($obj->$field) ?
                '/storage/img/' . $obj->$field
                : null;
            };

            $obj->event('store', $store)
            ->event('trash', $trash)
            ->event('date', $date)
            ->event('hydrate', $hydrate)
            ->event('tab', $tab)
            ->event('asset', $asset)
            ->event('display', $display);

            $functions = Arrays::exists('functions', $this->settings) ? $this->settings['functions'] : array();

            if (count($functions)) {
                foreach ($functions as $closureName => $closureAction) {
                    $share = function () use ($obj, $closureAction) {
                        return $closureAction($obj);
                    };
                    $obj->event($closureName, $share);
                }
            }

            foreach ($this->fields as $field => $infos) {
                $value = Arrays::exists($field, $data) ? $data[$field] : null;
                $obj->$field = $value;
            }

            if (Arrays::isAssoc($data)) {
                foreach ($data as $k => $v) {
                    if (!isset($obj->$k)) {
                        $obj->$k = $v;
                    }
                }
            }
            return $obj;
        }

        public function save($obj)
        {
            $data       = $obj->tab();
            $fields     = $this->fields;
            $id         = Arrays::exists('id', $data) ? $data['id'] : $this->makeKey();
            $new        = !Arrays::exists('id', $data);
            $datemodify = time();
            $checkTuple = Arrays::exists('checkTuple', $this->settings)
            ? $this->settings['checkTuple']
            : null;

            $store = array();

            if (true === $new) {
                $datecreate = time();
            } else {
                $datecreate = $data['date_create'];
            }

            if (count($data) && Arrays::isAssoc($data)) {
                foreach ($fields as $field => $info) {
                    $val = Arrays::exists($field, $data) ? $data[$field] : null;
                    if (empty($val)) {
                        if (!Arrays::exists('canBeNull', $info)) {
                            if (!Arrays::exists('default', $info)) {
                                throw new Exception('The field ' . $field . ' cannot be null.');
                            } else {
                                $val = $info['default'];
                            }
                        }
                    } else {
                        if (Arrays::exists('sha1', $info)) {
                            if (!preg_match('/^[0-9a-f]{40}$/i', $val) || strlen($val) != 40) {
                                $val = sha1($val);
                            }
                        } elseif (Arrays::exists('md5', $info)) {
                            if (!preg_match('/^[0-9a-f]{32}$/i', $val) || strlen($val) != 32) {
                                $val = md5($val);
                            }
                        } elseif (Arrays::exists('checkValue', $info)) {
                            $closure = $info['checkValue'];
                            if ($closure instanceof Closure) {
                                $val = $closure($val);
                            }
                        }
                    }
                    $store[$field] = $val;
                }
                if (!empty($checkTuple)) {
                    if (is_string($checkTuple)) {
                        $cond = "$checkTuple = " . $store[$checkTuple];
                        $res = $this->query($cond);
                    }
                    if (Arrays::is($checkTuple)) {
                        $query  = '';
                        foreach ($checkTuple as $ct) {
                            $query .= $ct . ' = ' . $store[$ct] . ' && ';
                        }
                        $query  = substr($query, 0, -4);

                        $tabConditions  = explode(' && ', $query);
                        $init           = true;
                        foreach ($tabConditions as $cond) {
                            $res = $this->query($cond);
                            if (true === $init) {
                                $init    = false;
                                $results = $res;
                            } else {
                                $results = array_intersect($results, $res);
                            }
                        }
                        $res    = $this->fetch($results);
                    }
                    if (count($res) && !Arrays::in($id, $res)) {
                        $tupleId = is_object(Arrays::first($res)) ? Arrays::first($res)->getId() : Arrays::first($res);
                        return $this->row($tupleId);
                    }
                }
                if (true === $new) {
                    $q = "INSERT INTO datas_" . $this->type . " (id, datecreate, datemodify, value) VALUES (
                        " . $this->quote($id) . ",
                        " . $this->quote($datecreate) . ",
                        " . $this->quote($datemodify) . ",
                        " . $this->quote($this->encode($store)) . "
                    )";
                } else {
                    $q = "UPDATE datas_" . $this->type . " SET
                        datemodify = " . $this->quote($datemodify) . ",
                        value = " . $this->quote($this->encode($store)) . "
                        WHERE id = " . $this->quote($id);
                }
                if (false === $this->transac) {
                    $this->_query($q);
                } else {
                    array_push($this->transactions, $q);
                }
                $data = $this->session->getData();
                $store['id'] = $id;
                $store['date_create'] = $datecreate;
                $row = $this->make($store);
                $data[$id] = $row;
                $this->session->setData($data);
                return $row;
            }
            return $obj;
        }

        public function delete($obj)
        {
            $q = "DELETE FROM datas_" . $this->type . " WHERE id = " . $this->quote($obj->getId());
            $data = $this->session->getData();
            if (Arrays::exists($obj->getId(), $data)) {
                unset($data[$obj->getId()]);
                $this->session->setData($data);
            }
            if (false === $this->transac) {
                $this->_query($q);
            } else {
                array_push($this->transactions, $q);
            }
        }

        public function commit()
        {
            if (count($this->transactions)) {
                foreach ($this->transactions as $q) {
                    $this->_query($q);
                }
            }
            return $this;
        }

        public function find($id)
        {
            return $this->findBy('id', $id, true);
        }

        public function findBy($field, $value, $one = false)
        {
            $res = $this->query("$field = $value");
            $this->reset();
            if (count($res) && true === $one) {
                return $this->row(Arrays::first($res));
            }
            return $res;
        }

        public function fetch($results = array())
        {
            $res = count($results) ? $results : $this->results;
            $collection = array();
            if (count($res)) {
                foreach ($res as $id) {
                    array_push($collection, $this->row($id));
                }
            }
            $this->reset();
            return $collection;
        }

        public function first($results = array())
        {
            $res = count($results) ? $results : $this->results;
            $row = null;

            if (count($res)) {
                $row = $this->row(Arrays::first($res));
            }

            $this->reset();
            return $row;
        }

        public function last($results = array())
        {
            $res = count($results) ? $results : $this->results;
            $row = null;

            if (count($res)) {
                $row = $this->row(Arrays::last($res));
            }

            $this->reset();
            return $row;
        }

        public function row($id)
        {
            if (strlen($id) != 9) {
                return null;
            }
            $s = $this->session;
            $data = $s->getData();
            $row = Arrays::exists($id, $data) ? $data[$id] : null;
            if (empty($row)) {
                $q = "SELECT datecreate, datemodify, value FROM datas_" . $this->type . " WHERE id = " . $this->quote($id);
                $res = $this->_query($q);

                if (Arrays::is($res)) {
                    $count = count($res);
                } else {
                    $count = $res->rowCount();
                }

                if (0 < $count) {
                    foreach ($res as $tmp) {
                        $tab = $this->decode($tmp['value']);
                        $tab['id'] = $id;
                        $tab['date_create'] = $tmp['datecreate'];
                        $tab['date_modify'] = $tmp['datemodify'];
                    }

                    $row = $this->make($tab);
                    $data[$id] = $row;
                    $s->setData($data);
                }
            }
            return $row;
        }

        public function exists($key)
        {
            $q = "SELECT COUNT(id) AS nb FROM datas_" . $this->type . " WHERE id = " . $this->quote($key);
            $res = $this->_query($q);
            foreach ($res as $tmp) {
                $count = $tmp['nb'];
            }
            return $count < 1 ? false : true;
        }

        public function makeKey($keyLength = 9)
        {
            $key    = Inflector::quickRandom($keyLength);
            $check  = $this->exists($key);

            if (true === $check) {
                return $this->makeKey();
            }
            return $key;
        }

        public function all($return = false)
        {
            $q = "SELECT id FROM datas_" . $this->type;
            $res = $this->_query($q);

            if (Arrays::is($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            foreach ($res as $tmp) {
                $id = $tmp['id'];
                $this->row($id);
            }
            if (true === $return) {
                return $this->session->getData();
            }
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
            $res = $this->query($condition, $results);
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

        public function query($condition, $results = array())
        {
            $collection = array();
            $datas = !count($results) ? $this->all(true) : $results;
            if(count($datas)) {
                $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                $condition  = repl('NOT IN', 'NOTIN', $condition);

                list($field, $op, $value) = explode(' ', $condition, 3);

                foreach ($datas as $id => $object) {
                    if (null !== $object->$field) {
                        $check = $this->compare($object->$field, $op, $value);
                    } else {
                        $check = ('null' == $value) ? true : false;
                    }
                    if (true === $check) {
                        array_push($collection, $id);
                    }
                }
            }
            return $collection;
        }

        private function compare($comp, $op, $value)
        {
            if (isset($comp)) {
                $comp   = Inflector::lower($comp);
                $value  = Inflector::lower($value);
                switch ($op) {
                    case '=':
                        return sha1($comp) == sha1($value);
                        break;
                    case '>=':
                        return $comp >= $value;
                        break;
                    case '>':
                        return $comp > $value;
                        break;
                    case '<':
                        return $comp < $value;
                        break;
                    case '<=':
                        return $comp <= $value;
                        break;
                    case '<>':
                    case '!=':
                        return sha1($comp) != sha1($value);
                        break;
                    case 'LIKE':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (strstr($comp, $value)) {
                            return true;
                        }
                        break;
                    case 'NOTLIKE':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (!strstr($comp, $value)) {
                            return true;
                        }
                        break;
                    case 'LIKESTART':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        return (substr($comp, 0, strlen($value)) === $value);
                        break;
                    case 'LIKEEND':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (!strlen($comp)) {
                            return true;
                        }
                        return (substr($comp, -strlen($value)) === $value);
                        break;
                    case 'IN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        return Arrays::in($comp, $tabValues);
                        break;
                    case 'NOTIN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        return !Arrays::in($comp, $tabValues);
                        break;
                }
            }
            return false;
        }

        public function selectAll()
        {
            $this->results = array_keys($this->all(true));
            return $this;
        }

        public function groupBy($field, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $groupBys   = array();
            $ever       = array();
            foreach ($res as $key => $id) {
                $object = $this->row($id);
                $getter = getter($field);
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
                foreach ($res as $key => $id) {
                    $object = $this->row($id);
                    $getter = getter($field);
                    $sum += $object->$getter();
                }
            }
            $this->reset();
            return $sum;
        }

        public function avg($field, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $sum = 0;
            if (count($res)) {
                foreach ($res as $key => $id) {
                    $object = $this->row($id);
                    $getter = getter($field);
                    $sum += $object->$getter();
                }
            }
            $this->reset();
            return ($sum / count($res));
        }

        public function min($field, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $min = 0;
            if (count($res)) {
                $first = true;
                foreach ($res as $key => $id) {
                    $object = $this->row($id);
                    $getter = getter($field);
                    $val    = $object->$getter();
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
                foreach ($res as $key => $id) {
                    $object = $this->row($id);
                    $getter = getter($field);
                    $val    = $object->$getter();
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

        public function order($fieldOrder = 'date_create', $orderDirection = 'ASC', $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $fields                 = $this->fields;
            $fields['id']           = array();
            $fields['date_create']  = array();
            if (!Arrays::is($fieldOrder)) {
                if (null !== $fieldOrder && !Arrays::exists($fieldOrder, $fields)) {
                    $fields[$fieldOrder] = array();
                }
            } else {
                foreach ($fields as $tmpField => $info) {
                    if (null !== $tmpField && !Arrays::exists($tmpField, $fields)) {
                        $fields[$tmpField] = array();
                    }
                }
            }
            $sort = array();
            foreach($res as $id) {
                $objectCreated = $this->row($id);
                foreach ($fields as $key => $infos) {
                    $type = Arrays::exists('type', $fields[$key]) ? $fields[$key]['type'] : null;
                    if ('data' == $type) {
                        list($dummy, $foreignTable, $foreignFieldKey) = $fields[$key]['contentList'];

                        $foreignFields = Arrays::exists($foreignTable, Data::$_fields)
                        ? Data::$_fields[$foreignTable]
                        : Data::noConfigFields($foreignTable);

                        $foreignStorage = new self($foreignTable);
                        $foreignRow = $foreignStorage->find($objectCreated->$key);

                        $foreignFieldKeys = explode(',', $foreignFieldKey);
                        $value = '';
                        for ($i = 0; $i < count($foreignFieldKey); $i++) {
                            $tmpKey = $foreignFieldKey[$i];
                            $value .= $foreignRow->$tmpKey . ' ';
                        }
                        $value = substr($value, 0, -1);
                    } else {
                        $value = isset($objectCreated->$key) ? $objectCreated->$key : null;
                    }
                    $sort[$key][] = $value;
                }
            }

            $asort = array();
            foreach ($sort as $key => $rows) {
                for ($i = 0 ; $i < count($rows) ; $i++) {
                    if (empty($$key) || is_string($$key) || !Arrays::is($$key)) {
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
                $tmpId = $row['id'];
                array_push($collection, $tmpId);
            }
            $this->results = $collection;

            return $this;
        }

        public function __call($method, $parameters)
        {
            if (substr($method, 0, 6) == 'findBy') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 6)));
                $field = Inflector::lower($uncamelizeMethod);
                $value = Arrays::first($parameters);
                return $this->findBy($field, $value);
            } elseif (substr($method, 0, 9) == 'findOneBy') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 9)));
                $field = Inflector::lower($uncamelizeMethod);
                $value = Arrays::first($parameters);
                return $this->findBy($field, $value, true);
            }
        }

        public function __toString()
        {
            return $this->type;
        }

        protected function quote($value, $parameterType = PDO::PARAM_STR)
        {
            if(null === $value) {
                return "NULL";
            }

            if (is_string($value)) {
                return $this->db->quote($value, $parameterType);
            }
            return $value;
        }

        private function encode($str)
        {
            return json_encode($str);
        }

        private function decode($str)
        {
            return json_decode($str, true);
        }
    }
