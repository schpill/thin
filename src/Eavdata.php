<?php
    namespace Thin;
    class Eavdata
    {
        private static $_dataClass = 'Thin\\Data';

        public static function setDataClass($class)
        {
            static::$_dataClass = 'Thin\\' . $class;
        }

        private static function _exec($method, array $args = array())
        {
            return call_user_func_array(array(static::$_dataClass, $method), $args);
        }

        public static function add($entity, array $data)
        {
            $datas              = array();
            $datas['entity']    = $entity;
            $row                = new EAVRow;
            $datas['data']      = $row->populate($data);
            // $datas['fields']    = array();
            // foreach ($data as $key => $value) {
            //     $datas['fields'][] = $key;
            // }
            return static::_exec('add', array('eavrecord', $datas));
        }

        public function edit($entity, $id, array $data)
        {
            $datas              = array();
            $datas['entity']    = $entity;
            $row                = new EAVRow;
            $datas['data']      = $row->populate($data);
            // $datas['fields']    = $row->_fields;
            // foreach ($data as $key => $value) {
            //     $datas['fields'][] = $key;
            // }
            return static::_exec('edit', array('eavrecord', $id, $datas));
        }

        public function delete($id)
        {
            return static::_exec('delete', array('eavrecord', $id));
        }

        public static function getById($id)
        {
            return static::_exec('getById', array('eavrecord', $id));
        }

        public static function getAll($entity)
        {
            return static::_exec('getAll', array('eavrecord'));
        }

        public static function fields($entity)
        {
            $datas = static::getAll($entity);
            $row = current($datas);
            $obj = static::_exec('getObject', array($row, 'eavrecord'));
            $row = $obj->getData();
            // $fields = $obj->getFields();
            return $row->_fields;
        }

        public static function query($entity, $conditions = '', $offset = 0, $limit = 0, $orderField = null, $orderDirection = 'ASC')
        {
            $dataClass = static::$_dataClass;
            static::_exec('_incQueries', array(static::_exec('_getTime', array())));
            $queryKey  = sha1(serialize(func_get_args()));

            if (true === $dataClass::$_buffer) {
                $buffer = static::_exec('_buffer', array($queryKey));
                if (false !== $buffer) {
                    return $buffer;
                }
            }

            $results                = array();
            $resultsAnd             = array();
            $resultsOr              = array();
            $resultsXor             = array();

            $fields                 = static::fields($entity);
            if (!Arrays::isArray($orderField)) {
                if (null !== $orderField && !ake($orderField, $fields)) {
                    $fields[$orderField] = array();
                }
            } else {
                foreach ($orderField as $tmpField) {
                    if (null !== $tmpField && !ake($tmpField, $fields)) {
                        $fields[$tmpField] = array();
                    }
                }
            }
            $datas = static::getAll($entity);
            if(!count($datas)) {
                return $results;
            }

            if (!strlen($conditions)) {
                $conditionsAnd  = array();
                $conditionsOr   = array();
                $conditionsXor  = array();
                $results        = $datas;
            } else {
                $conditionsAnd  = explode(' && ',   $conditions);
                $conditionsOr   = explode(' || ',   $conditions);
                $conditionsXor  = explode(' XOR ',  $conditions);
            }

            if (count($conditionsOr) == count($conditionsAnd)) {
                if (current($conditionsOr) == current($conditionsAnd)) {
                    $conditionsAnd = array();
                }
            }

            if (count($conditionsXor) == count($conditionsOr)) {
                if (current($conditionsXor) == current($conditionsOr)) {
                    $conditionsXor = array();
                }
            }

            if (count($conditionsAnd)) {
                foreach ($conditionsAnd as $condition) {
                    foreach ($datas as $tmpObject) {
                        $object = static::_exec('getObject', array($tmpObject, 'eavrecord'));
                        if (!is_object($object)) {
                            continue;
                        }
                        $object = $object->getData()->setId($object->getId());

                        $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                        $condition  = repl('NOT IN', 'NOTIN', $condition);

                        list($field, $op, $value) = explode(' ', $condition, 3);

                        $continue = true;

                        if (null !== $object->$field) {
                            $continue = static::_exec('analyze', array($object->$field, $op, $value));
                        } else {
                            $continue = false;
                        }
                        if (true === $continue) {
                            if (!count($resultsAnd)) {
                                array_push($resultsAnd, $tmpObject);
                            } else {
                                $tmpResult = array($tmpObject);
                                $resultsAnd = array_intersect($resultsAnd, $tmpResult);
                            }
                        }
                    }
                }
                if (!count($results)) {
                    $results = $resultsAnd;
                } else {
                    $results = array_intersect($results, $resultsAnd);
                }
            }

            if (count($conditionsOr)) {
                foreach ($conditionsOr as $condition) {
                    foreach ($datas as $tmpObject) {
                        $object = static::_exec('getObject', array($tmpObject, 'eavrecord'));
                        if (!is_object($object)) {
                            continue;
                        }
                        $object = $object->getData()->setId($object->getId());

                        $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                        $condition  = repl('NOT IN', 'NOTIN', $condition);

                        list($field, $op, $value) = explode(' ', $condition, 3);

                        if (!isset($object->$field)) {
                            $continue = false;
                        } else {
                            if (null !== $object->$field) {
                                $continue = static::_exec('analyze', array($object->$field, $op, $value));
                            } else {
                                $continue = false;
                            }
                        }
                        if (true === $continue) {
                            if (!count($resultsOr)) {
                                array_push($resultsOr, $tmpObject);
                            } else {
                                $tmpResult = array($tmpObject);
                                $resultsOr = array_merge($resultsOr, $tmpResult);
                            }
                        }

                    }
                }
                if (!count($results)) {
                    $results = $resultsOr;
                } else {
                    $results = array_merge($results, $resultsOr);
                }
            }

            if (count($conditionsXor)) {
                foreach ($conditionsXor as $condition) {
                    foreach ($datas as $tmpObject) {
                        $object = static::_exec('getObject', array($tmpObject, 'eavrecord'));
                        if (!is_object($object)) {
                            continue;
                        }
                        $object = $object->getData()->setId($object->getId());

                        $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                        $condition  = repl('NOT IN', 'NOTIN', $condition);

                        list($field, $op, $value) = explode(' ', $condition, 3);

                        $continue = true;

                        if (null !== $object->$field) {
                            $continue = static::_exec('analyze', array($object->$field, $op, $value));
                        } else {
                            $continue = false;
                        }
                        if (true === $continue) {
                            if (!count($resultsXor)) {
                                array_push($resultsXor, $tmpObject);
                            } else {
                                $tmpResult = array($tmpObject);
                                $resultsXor = array_merge(array_diff($resultsXor, $tmpResult), array_diff($tmpResult, $resultsXor));
                            }
                        }

                    }
                }
                if (!count($results)) {
                    $results = $resultsXor;
                } else {
                    $results = array_merge(array_diff($results, $resultsXor), array_diff($resultsXor, $results));
                }
            }

            if (count($results)) {
                if (0 < $limit) {
                    $max = count($results);
                    $number = $limit - $offset;
                    if ($number > $max) {
                        $offset = $max - $limit;
                        if (0 > $offset) {
                            $offset = 0;
                        }
                        $limit = $max;
                    }
                    $results = array_slice($results, $offset, $limit);
                }
            }

            if (count($results) && null !== $orderField) {
                if (Arrays::isArray($orderField)) {
                    $orderFields = $orderField;
                } else {
                    $orderFields = array($orderField);
                }
                foreach ($orderFields as $orderField) {
                    $sort = array();
                    foreach($results as $object) {
                        $objectCreated = static::_exec('getObject', array($object, 'eavrecord'));
                        foreach ($fields as $key => $infos) {
                            $value = isset($objectCreated->$key) ? $objectCreated->$key : null;
                            $sort[$key][] = $value;
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

                    if ('ASC' == Inflector::upper($orderDirection)) {
                        array_multisort($$orderField, SORT_ASC, $asort);
                    } else {
                        array_multisort($$orderField, SORT_DESC, $asort);
                    }
                    $collection = array();
                    foreach ($asort as $key => $row) {
                        $tmpId = $row['id'];
                        $tmpObject = static::getById($tmpId);
                        array_push($collection, $tmpObject);
                    }

                    $results = $collection;
                }
            }

            if (true === $dataClass::$_buffer) {
                static::_exec('_buffer', array($queryKey, $results));
            }

            return $results;
        }

        public static function returnArray($obj)
        {
            $row = $obj->getData();
            $fields = $row->_fields;
            $array = array();
            foreach ($fields as $field) {
                $array[$field] = $row->$field;
            }
            return $array;
        }
    }
