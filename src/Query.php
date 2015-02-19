<?php
    namespace Thin;

    class Query
    {
        private $db;
        private $joins                  = array();
        private $customizeCollection    = array();
        private $customize              = array();

        public function __construct(Database $db)
        {
            $this->db = $db;
        }

        public function join(Database $db, $field = null, $fkField = null, $type = 'LEFT JOIN')
        {
            $args = array();
            $args[] = $db;
            $args[] = is_null($field) ? $db->database . '.' . $db->table . '.' . 'id' : $db->database . '.' . $db->table . '.' . $field;
            $args[] = is_null($fkField) ? $this->db->database . '.' . $this->db->table . '.' . $db->table . '_id' : $fkField;
            $args[] = $type;
            array_push($this->joins, $args);
            return $this;
        }

        public function where($condition, $op = 'AND')
        {
            $tab = explode(' ', $condition);
            $oldField = $field = Arrays::first($tab);
            if (!strstr($field, '.')) {
                $field = $this->db->database . '.' . $this->db->table . '.' . $field;
                $condition = strReplaceFirst($oldField, $field, $condition);
            }
            $this->db = $this->db->where($condition, $op);
            return $this;
        }

        public function where_and($condition)
        {
            return $this->where($condition);
        }

        public function where_or($condition)
        {
            return $this->where($condition, 'OR');
        }

        public function where_xor($condition)
        {
            return $this->where($condition, 'XOR');
        }

        public function sum($field)
        {
            return $this->db->sum($field);
        }

        public function avg($field)
        {
            return $this->db->avg($field);
        }

        public function min($field)
        {
            return $this->db->min($field);
        }

        public function max($field)
        {
            return $this->db->max($field);
        }

        public function sort($field, $direction = 'ASC')
        {
            $this->db = $this->db->order($field, $direction);
            return $this;
        }

        public function limit($limit, $offset = 0)
        {
            $this->db = $this->db->limit($limit, $offset);
            return $this;
        }

        public function groupBy($field)
        {
            $this->db = $this->db->groupBy($field);
            return $this;
        }

        public function run($object = false)
        {
            $joined = count($this->joins) ? true : false;
            if (false === $joined) {
                $collection = $this->db->exec($object);
            } else {
                $collection = $this->results($joined, $object);
            }
            if (count($collection)) {
                if (count($this->customize)) {
                    $newCollection = array();
                    foreach ($this->customize as $customize) {
                        if (is_callable($customize)) {
                            foreach ($collection as $row) {
                                $newRow = $customize($row);
                                array_push($newCollection, $newRow);
                            }
                        }
                    }
                    $collection = $newCollection;
                }
            }
            if (count($collection)) {
                if (count($this->customizeCollection)) {
                    foreach ($this->customizeCollection as $customizeCollection) {
                        if (is_callable($customizeCollection)) {
                            $collection = $customizeCollection($collection);
                        }
                    }
                }
            }
            return $collection;
        }

        public function first($object = false)
        {
            return $this->elem('first', $object);
        }

        public function last($object = false)
        {
            return $this->elem('last', $object);
        }

        public function count()
        {
            return $this->elem('count', false);
        }

        public function paginate($page = 1, $limit = 25, $object = false)
        {
            $collection = $this->run($object);
            $paginator = Paginator::make($collection, count($collection), $limit);
            return $paginator;
        }

        public function toJson()
        {
            $collection = $this->run();
            if (count($collection)) {
                $cleanedCollection = array();
                foreach ($collection as $row) {
                    foreach ($row as $key => $value) {
                        if (is_object($value)) {
                            unset($row[$key]);
                        } else {
                            if (false === Utils::isUtf8($value)) {
                                $row[$key] = utf8_encode($value);
                            }
                        }
                    }
                    array_push($cleanedCollection, $row);
                }
                $collection = $cleanedCollection;
            }
            return json_encode($collection, JSON_PRETTY_PRINT);
        }

        private function elem($type, $object = false)
        {
            $collection = $this->run($object);
            if (count($collection)) {
                if ('first' == $type) {
                    $collection = Arrays::first($collection);
                } elseif ('last' == $type) {
                    $collection = Arrays::last($collection);
                } elseif ('count' == $type) {
                    $collection = count($collection);
                }
            } else {
                if ('count' == $type) {
                    $collection = 0;
                }
            }
            return $collection;
        }

        private function results($joined, $object)
        {
            if (false === $joined) {
                $fields = $this->db->database . "." . $this->db->table . ".*";
                $query = "SELECT $fields FROM " . $this->db->database . "." . $this->db->table . " WHERE ";
            } else {
                $fields = '';
                $dbFields = $this->db->map['fields'];
                foreach ($dbFields as $tmpField => $tmpInfos) {
                    $fields .= $this->db->database . "." . $this->db->table . "." . $tmpField . " AS " . $tmpField . ", ";
                }
                $fields = substr($fields, 0, -2);
                $query = "SELECT $fields FROM " . $this->db->database . "." . $this->db->table;
                foreach ($this->joins as $join) {
                    list($db, $fieldOriginal, $fieldTarget, $type) = $join;
                    $query .= "\n" . Inflector::upper($type) . ' ' .$db->database . '.' . $db->table . ' ON ' . $fieldTarget . ' = ' . $fieldOriginal;
                }
                $query .= "\n" . 'WHERE ';
            }
            if (count($this->db->wheres)) {
                $first = true;
                foreach ($this->db->wheres as $where) {
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

            if (count($this->db->groupBys)) {
                $query .= ' GROUP BY ';
                $first = true;
                foreach ($this->db->groupBys as $groupBy) {
                    if (false === $first) {
                        $query .= ", $this->database.$this->table.$groupBy";
                    } else {
                        $query .= $groupBy;
                    }
                    $first = false;
                }
            }

            if (count($this->db->orders)) {
                $query .= ' ORDER BY ';
                $first = true;
                foreach ($this->db->orders as $order) {
                    list($field, $direction) = $order;
                    if (false === $first) {
                        $query .= ", $this->database.$this->table.$field $direction";
                    } else {
                        $query .= "$this->database.$this->table.$field $direction";
                    }
                    $first = false;
                }
            }

            if (isset($this->db->limit)) {
                $offset = isset($this->db->offset) ? $this->db->offset : 0;
                $query .= ' LIMIT ' . $offset . ', ' . $this->db->limit;
            }

            $this->db->query = $query;
            $results = $this->db->fetch($query);
            $collection = $this->db->exec($object, $results);
            if (true === $joined && count($collection)) {
                return $this->related($collection, $object);
            }
            return $collection;
        }

        private function related($collection, $object)
        {
            $newCollection = array();
            foreach ($collection as $row) {
                foreach ($this->joins as $join) {
                    list($db, $fieldOriginal, $fieldTarget, $type) = $join;
                    $field = $db->table;
                    $cb = function () use ($row, $fieldTarget, $db) {
                        return $db->find($row->$fieldTarget);
                    };
                    if (true === $object) {
                        $row->$field = $cb;
                    } else {
                        $row[$field] = $cb;
                    }
                }
                array_push($newCollection, $row);
            }
            return $newCollection;
        }

        public function customizeCollection($cb)
        {
            array_push($this->customizeCollection, $cb);
            return $this;
        }

        public function customize($cb)
        {
            array_push($this->customize, $cb);
            return $this;
        }
    }
