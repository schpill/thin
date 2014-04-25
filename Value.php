<?php
    namespace Thin;
    use SQLite3;

    class Value
    {
        private $results;
        private $wheres = array();
        private $type;
        private $key;
        private $db;

        public function __construct($type)
        {
            $this->type = $type;

            $dbFile     = STORAGE_PATH . DS . Inflector::camelize('value_' . $type) . '.store';
            $this->db   = new SQLite3($dbFile);

            $q          = "SELECT * FROM sqlite_master WHERE type = 'table' AND name = 'datas'";
            $res        = $this->db->query($q);
            if(false === $res->fetchArray()) {
                $this->db->exec('CREATE TABLE datas (key, value, expiration)');
            }
            $this->clean();
        }

        public function reset()
        {
            unset($this->results);
            $this->wheres = array();
            return $this;
        }

        private function clean()
        {
            $q = "DELETE FROM datas WHERE expiration <= " . time();
            $this->db->exec($q);
        }

        public function set($key, $value, $expiration = 9999999999)
        {
            return $this->save($key, $value, $expiration);
        }

        public function save($key, $value, $expiration = 9999999999)
        {
            $q = "DELETE FROM datas WHERE key = '" . SQLite3::escapeString($key) . "'";
            $this->db->exec($q);

            $q = "INSERT INTO datas (key, value, expiration) VALUES (
                '" . SQLite3::escapeString($key) . "',
                '" . SQLite3::escapeString(json_encode($value)) . "',
                '" . SQLite3::escapeString($expiration) . "'
            )";
            $this->db->exec($q);

            return $this;
        }

        public function delete($key)
        {
            $q = "DELETE FROM datas WHERE key = '" . SQLite3::escapeString($key) . "'";
            $this->db->exec($q);
        }

        public function get($key = null)
        {
            if (!empty($key)) {
                $q      = "SELECT value FROM datas WHERE key = '" . SQLite3::escapeString($key) . "'";
                $res    = $this->db->query($q);

                while ($tmp = $res->fetchArray(SQLITE3_ASSOC)) {
                    return json_decode($tmp['value'], true);
                }
            } else {
                $collection = array();
                if (!empty($this->results)) {
                    foreach ($this->results as $id => $row) {
                        array_push($collection, $this->row($row));
                    }
                }
                return $collection;
            }
            return null;
        }

        public function key($key)
        {
            $this->key = $key;
            return $this;
        }

        public function fetch($key)
        {
            $this->results = $this->get($key);
            return $this;
        }

        public function incr($key, $nb = 1)
        {
            $counter = $this->get($key);
            $counter = empty($counter) ? 0 : $counter;
            $counter += $nb;
            return $this->save($key, $counter);
        }

        public function decr($key, $nb = 1)
        {
            $counter = $this->get($key);
            $counter = empty($counter) ? 0 : $counter;
            $counter -= $nb;
            $counter = 0 > $counter ? 0 : $counter;
            return $this->save($key, $counter);
        }

        public function push($key, $value)
        {
            $list = $this->get($key);
            $list = empty($list) ? array() : $list;
            $id = isAke($value, 'id', null);
            if (strlen($id)) {
                unset($value['id']);
                return $this->edit($key, $id, $value, $list);
            }
            array_push($list, $value);
            return $this->save($key, $list);
        }

        public function edit($key, $index, $value, $list)
        {
            $list = empty($list) ? array() : $list;
            $collection = array();
            foreach ($list as $k => $v) {
                if ($k == $index) {
                    $collection[$k] = $value;
                }
            }
            return $this->save($key, $collection);
        }

        public function pop($key, $index)
        {
            $list = $this->get($key);
            $list = empty($list) ? array() : $list;
            $collection = array();
            foreach ($list as $k => $v) {
                if ($k != $index) {
                    $collection[$k] = $v;
                }
            }
            return $this->save($key, $collection);
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

        public function query($q)
        {
            $collection = array();
            $q          = "SELECT key FROM datas WHERE key LIKE '%" . SQLite3::escapeString($key) . "%'";
            $res        = $this->db->query($q);

            while ($tmp = $res->fetchArray(SQLITE3_ASSOC)) {
                array_push($collection, $this->get($tmp['key']));
            }

            return $collection;
        }

        public function row(array $values)
        {
            $class = $this;
            $obj = new Container;
            $key = $this->key;

            $record = function () use ($class, $obj, $key) {
                $tab = $obj->assoc();
                return $class->push($key, $tab);
            };

            $purge = function () use ($class, $obj, $key) {
                $tab = $obj->assoc();
                return $class->pop($key, $tab['id']);
            };

            $date = function ($f)  use ($obj) {
                return date('Y-m-d H:i:s', $obj->$f);
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
                return \Thin\Html\Helper::display($obj->$field);
            };

            $tab = function ()  use ($obj) {
                return $obj->assoc();
            };

            $asset = function ($field) use ($obj) {
                return '/storage/img/' . $obj->$field;
            };

            $obj->event('record', $record)
            ->event('purge', $purge)
            ->event('date', $date)
            ->event('hydrate', $hydrate)
            ->event('tab', $tab)
            ->event('asset', $asset)
            ->event('display', $display);
            return $obj->populate($values);
        }

        public function find($id)
        {
            return $this->findBy('id', $id, true);
        }

        public function findBy($field, $value, $one = false)
        {
            $res = $this->search("$field = $value");
            $this->reset();
            if (count($res) && true === $one) {
                return $this->row(Arrays::first($res));
            }
            return $this->get();
        }

        public function groupBy($field, $results = array())
        {
            $res = count($results) ? $results : $this->results;
            $groupBys   = array();
            $ever       = array();
            foreach ($res as $id => $tab) {
                $obj = isAke($tab, $field, null);
                if (!Arrays::in($obj, $ever)) {
                    $groupBys[$id]  = $tab;
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
                foreach ($res as $id => $tab) {
                    $val = isAke($tab, $field, null);
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
                    $val = isAke($tab, $field, null);
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
                    $val = isAke($tab, $field, null);
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
            $fields                 = array_keys(Arrays::first($res));

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

        public function search($condition, $results = array())
        {
            $collection = array();
            $datas = !count($results) ? $this->get($this->key) : $results;
            if(count($datas)) {
                $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                $condition  = repl('NOT IN', 'NOTIN', $condition);

                list($field, $op, $value) = explode(' ', $condition, 3);

                foreach ($datas as $id => $tab) {
                    $val = isAke($tab, $field, null);
                    if (!empty($val)) {
                        $check = $this->compare($val, $op, $value);
                    } else {
                        $check = ('null' == $value) ? true : false;
                    }
                    if (true === $check) {
                        $tab['id'] = $id;
                        array_push($collection, $tab);
                    }
                }
            }
            $this->results = $collection;
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
    }
