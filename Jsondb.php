<?php
    namespace Thin;

    class Jsondb
    {
        private $results;
        private $wheres = array();
        private $lock;
        private $db;
        private $type;
        private $cache = true;
        private $ttl = 3600;

        public function __construct($type)
        {
            $this->type = $type;

            $this->db   = STORAGE_PATH . DS . Inflector::camelize('json_db_' . $type) . '.store';
            $this->lock = STORAGE_PATH . DS . Inflector::camelize('json_db_' . $type) . '.lock';
            if (!File::exists($this->db)) {
                touch($this->db);
            }
        }

        public function reset()
        {
            unset($this->results);
            $this->wheres = array();
            return $this;
        }

        public function set($value)
        {
            return $this->save($value);
        }

        private function lock()
        {
            $this->waitUnlock();
            File::put($this->lock, time());
            return $this;
        }

        private function unlock()
        {
            File::delete($this->lock);
            return $this;
        }

        private function waitUnlock()
        {
            $wait = File::exists($this->lock);
            while (true == $wait) {
                usleep(100);
                $wait = File::exists($this->lock);
            }
            return $this;
        }

        public function save($data)
        {
            $this->lock();
            File::delete($this->db);
            $json = json_encode($data);
            File::put($this->db, $json);
            $this->cached('all_' . $this->type, $data);
            return $this->unlock();
        }

        public function all()
        {
            $data = $this->cached('all_' . $this->type);
            if (empty($cached)) {
                $data = fgc($this->db);
                $data = strlen($data) ? json_decode($data, true) : array();
            }
            return $data;
        }

        public function get()
        {
            return $this->all();
        }

        public function results($object = true)
        {
            if (false === $object) {
                return $this->results;
            }
            $collection = array();
            foreach ($this->results as $res) {
                $tmp = $this->row($res);
                array_push($collection, $tmp);
            }
            $this->reset();
            return $collection;
        }

        public function exec($object = false)
        {
            if (false === $object) {
                return $this->results;
            }
            $collection = array();
            foreach ($this->results as $res) {
                $tmp = $this->row($res);
                array_push($collection, $tmp);
            }
            $this->reset();
            return $collection;
        }

        public function fetch()
        {
            $this->results = $this->all();
            return $this;
        }

        public function push($value)
        {
            $list = $this->get();
            $id = isAke($value, 'id', null);
            if (strlen($id)) {
                unset($value['id']);
                return $this->edit($id, $value, $list);
            }
            array_push($list, $value);
            return $this->save($list);
        }

        public function edit($index, $value, $list)
        {
            $collection = array();
            foreach ($list as $k => $v) {
                if ($k == $index) {
                    $collection[$k] = $value;
                } else {
                    $collection[$k] = $v;
                }
            }
            return $this->save($collection);
        }

        public function pop($index)
        {
            $list = $this->all();
            $collection = array();
            foreach ($list as $k => $v) {
                if ($k != $index) {
                    $collection[$k] = $v;
                }
            }
            return $this->save($collection);
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
            return $this->where($q)->results();
        }

        public function toObject(array $array)
        {
            return $this->row($array);
        }

        public function row(array $values)
        {
            $class = $this;
            $class->results = null;
            $class->wheres = null;
            $obj = new Container;

            $record = function () use ($class, $obj) {
                return $class->push($obj->assoc());
            };

            $purge = function () use ($class, $obj) {
                return $class->pop($obj->getId());
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
                return Html\Helper::display($obj->$field);
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
            $keyCache = sha1('groupby' . $field . serialize($res) . $this->type);

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

            $keyCache = sha1('order' . serialize($fieldOrder) . serialize($orderDirection) . serialize($res) . $this->type);
            $cached = $this->cached($keyCache);

            if (empty($cached)) {
                $fields = array_keys(Arrays::first($res));

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

        public function search($condition, $results = array(), $populate = true)
        {
            $collection = array();
            $datas = !count($results) ? $this->all() : $results;

            $keyCache = sha1('search' . $condition . serialize($datas) . $this->type);

            $cached = $this->cached($keyCache);
            if (empty($cached)) {
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

        private function cached($key, $value = null)
        {
            if (false === $this->cache) {
                return null;
            }
            $file = STORAGE_PATH . DS . 'cache' . DS . $key;
            if (empty($value)) {
                if (File::exists($file)) {
                    $age = filemtime($file);
                    $maxAge = time() - $this->ttl;
                    if ($maxAge < $age) {
                        return json_decode(File::get($file), true);
                    } else {
                        File::delete($file);
                        return null;
                    }
                }
            } else {
                if (File::exists($file)) {
                    File::delete($file);
                }
                File::put($file, json_encode($value));
                return true;
            }
        }

        public function setCache($bool = true)
        {
            $this->cache = $bool;
            return $this;
        }

        public function setTtl($ttl = 3600)
        {
            $this->ttl = $ttl;
            return $this;
        }
    }
