<?php
    namespace Thin;
    class Querydata
    {
        private $type;
        private $wheres     = array();
        private $offset     = 0;
        private $limit      = 0;
        private $fields;
        private $settings;
        private $results;
        private $firstQuery = true;

        public function __construct($type, $results = array())
        {
            $settings   = Arrays::exists($type, Data::$_settings)  ? Data::$_settings[$type]   : Data::defaultConfig($type);
            $fields     = Arrays::exists($type, Data::$_fields)    ? Data::$_fields[$type]     : Data::noConfigFields($type);

            $this->type     = $type;
            $this->fields   = $fields;
            $this->settings = $settings;
            $this->results  = $results;
        }

        public function all()
        {
            $queryKey   = sha1($this->type . 'getAll');
            $cache      = Data::cache($this->type, $queryKey);

            if (!empty($cache)) {
                $this->results = $cache;
                return $this;
            }

            $datas      = Data::getAll($this->type);
            if (count($datas)) {
                foreach ($datas as $path) {
                    $object = Data::getObject($path);
                    array_push($this->results, $object);
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

            if (!empty($cache)) {
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

        public function whereAnd($condition)
        {
            $collection = $this->query($condition);
            $this->resultsAnd($collection);
            return $this;
        }

        public function whereOr($condition)
        {
            $collection = $this->query($condition);
            $this->resultsOr($collection);
            return $this;
        }

        public function whereXor($condition)
        {
            $collection = $this->query($condition);
            $this->resultsXor($collection);
            return $this;
        }

        public function where($condition)
        {
            return $this->whereOr($condition);
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

        public function order($orderFields, $orderDirections = 'ASC')
        {
            if (count($this->results) && null !== $orderFields) {
                $queryKey   = sha1(serialize($this->wheres) . serialize($orderFields) . serialize($orderDirections));
                $cache      = Data::cache($this->type, $queryKey);

                if (!empty($cache)) {
                    $this->results = $cache;
                    return $this;
                }

                if (Arrays::isArray($orderFields)) {
                    $orderFields = $orderFields;
                } else {
                    $orderFields = array($orderFields);
                }

                if (Arrays::isArray($orderDirections)) {
                    $orderDirections = $orderDirections;
                } else {
                    $orderDirections = array($orderDirections);
                }
                $cnt = 0;
                foreach ($orderFields as $orderField) {
                    $sort = array();
                    foreach($this->results as $id) {
                        $objectCreated           = (is_string($id)) ? Data::getById($this->type, $id) : $id;
                        $sort['id'][]            = $objectCreated->id;
                        $sort['date_create'][]   = $objectCreated->date_create;

                        foreach ($this->fields as $k => $infos) {
                            $value      = isset($objectCreated->$k) ? $objectCreated->$k : null;
                            $sort[$k][] = $value;
                        }
                    }

                    $asort = array();
                    foreach ($sort as $k => $rows) {
                        for ($i = 0 ; $i < count($rows) ; $i++) {
                            if (empty($$k) || is_string($$k) || is_object($$k)) {
                                $$k = array();
                            }
                            $asort[$i][$k] = $rows[$i];
                            array_push($$k, $rows[$i]);
                        }
                    }

                    $orderDirection = isset($orderDirections[$cnt]) ? $orderDirections[$cnt] : Arrays::first($orderDirections);

                    if (count($$orderField) != count($asort)) {
                        $newTab = array();
                        $h = $$orderField;
                        for ($i = count($asort) + 1, $j = count($h) + 1; $i >= 0 ; $i--, $j--) {
                            if (isset($h[$j])) {
                                $newTab[$i] = $h[$j];
                            }
                        }
                        $$orderField = array_reverse($newTab);
                    }

                    if ('ASC' == Inflector::upper($orderDirection)) {
                        array_multisort($$orderField, SORT_ASC, $asort);
                    } else {
                        array_multisort($$orderField, SORT_DESC, $asort);
                    }
                    $collection = array();
                    foreach ($asort as $k => $row) {
                        $tmpId = $row['id'];
                        array_push($collection, $tmpId);
                    }
                    $cache = Data::cache($this->type, $queryKey, $collection);

                    $this->results = $collection;
                    $cnt++;
                }
            }
            return $this;
        }

        public function offset($offset)
        {
            $this->offset = $offset;
            return $this;
        }

        public function limit($limit)
        {
            $this->limit = $limit;
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
            return static::get($results);
        }

        public function get($results = null)
        {
            $resultsGet = null !== $results ? $results : $this->results;
            $queryKey   = sha1(serialize($this->wheres) . serialize($resultsGet));
            $cache      = Data::cache($this->type, $queryKey);

            if (!empty($cache)) {
                return $cache;
            }

            if (count($resultsGet)) {
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
                    $this->results = array_slice($resultsGet, $this->offset, $this->limit);
                }
            }
            $collection = array();
            if (count($resultsGet)) {
                foreach ($resultsGet as $key => $id) {
                    $object         = Data::getById($this->type, $id);
                    $collection[]   = $object;
                }
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

        public static function __callstatic($method, $parameters)
        {
            array_unshift($parameters, $this->type);
            return call_user_func_array(array("Thin\\Data", $method), $parameters);
        }
    }
