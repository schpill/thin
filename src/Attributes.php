<?php
    namespace Thin;
    class Attributes
    {
        public $dbe, $dba, $dbr, $entity;
        private $wgeres = array(), $cache = false, $ttl = 3600, $results;

        public function __construct($entity)
        {
            $session = session('atributes_eav_' . $entity);
            $this->entity = $entity;
            $this->models();
            $this->dbe = null === $session->getDbe() ? new Store($entity . '_eav') : $session->getDbe();
            $this->dba = null === $session->getDba() ? new Store('attributes_eav') : $session->getDba();
            $this->dbr = null === $session->getDbr() ? new Store('relations_eav') : $session->getDbr();

            $session->setDbe($this->dbe)->setDba($this->dba)->setDbr($this->dbr);
        }

        private function models()
        {
            $class = $this;

            $fields = array(
                'status'            => array('default' => 'active')
            );
            $conf = array(
                'functions'         => array(
                    'relations'     => function ($obj) use ($class) {
                        return $class->dbr->where('entity_name = ' . $class->entity)->fetch();
                    },
                    'fetchCollection'    => function ($obj) use ($class) {
                        return $class->fetchValues();
                    },
                    'attributes'     => function ($obj) use ($class) {
                        $collection     = array();
                        $tuples         = array();
                        $relations      = $class->dbr->where('entity = ' . $obj->getId())->fetch();
                        foreach ($relations as $relation) {
                            $att = $relation->getAttribute();
                            if (!Arrays::in($att, $tuples)) {
                                array_push($tuples, $att);
                                array_push($collection, $class->dba->find($att));
                            }
                        }
                        return $collection;
                    },
                )
            );
            data($this->entity . '_eav', $fields, $conf);

            $fields = array(
                'name'              => array('cantBeNull' => true)
            );
            $conf = array(
                'checkTuple'        => 'name',
                'functions'         => array(
                    'data'          => function ($obj) use ($class) {
                        return $class->data($obj);
                    },
                )
            );
            data('attributes_eav', $fields, $conf);

            $fields = array(
                'entity_name'       => array('default'     => $this->entity),
                'entity'            => array('cantBeNull'  => true),
                'attribute'         => array('cantBeNull'  => true),
                'value'             => array('cantBeNull'  => true)
            );
            $conf = array(
                'functions'         => array(
                    'entity'        => function ($obj) use ($class) {
                        return $class->dbe->find($obj->getEntity());
                    },
                    'attribute'     => function ($obj) use ($class) {
                        return $class->dba->find($obj->getAttribute());
                    },
                )
            );
            data('relations_eav', $fields, $conf);
        }

        public function record()
        {
            return new Container;
        }

        public function make($data)
        {
            $data = is_object($data) && $data instanceof Container ? $data->assoc() : $data;
            if (!Arrays::isAssoc($data)) {
                throw new Exception("This method needs a valid object to process.");
            }
            $e = $data['entity'] = $this->entity;

            $class = $this;
            $obj = new Container;

            $store = function () use ($obj, $e) {
                $class = new Attributes($e);
                return $class->save($obj);
            };

            $remove = function () use ($obj, $e) {
                $class = new Attributes($e);
                return $class->delete($obj);
            };

            $date = function ($f) use ($obj) {
                return date('Y-m-d H:i:s', $obj->$f);
            };

            $display = function ($f, $echo = true) use ($obj) {
                if (false === $echo) {
                    return Html\Helper::display($obj->$f);
                } else {
                    echo Html\Helper::display($obj->$f);
                }
            };

            $obj->event('store', $store)
            ->event('trash', $remove)
            ->event('date', $date)
            ->event('display', $display);

            foreach ($data as $k => $v) {
                if (!isset($obj->$k)) {
                    $obj->$k = $v;
                }
            }
            return $obj;
        }

        public function delete($object)
        {
            if (isset($object->id)) {
                $entity = $this->dbe->find($object->getId());
                $relations = $this->dbr
                ->where('entity = ' . $object->getId())
                ->fetch();
                foreach ($relations as $relation) {
                    $this->dbr->delete($relation);
                }
                $this->dbe->delete($entity);
            }
        }

        public function save($object)
        {
            $isNew = null === $object->getId();
            if (true === $isNew) {
                $entity = $this->dbe->make(array('status' => 'active'))->store();
                $object->date_create = time();
            } else {
                $entity = $this->dbe->find($object->getId());
            }
            $object->date_modify = time();

            $values = $object->assoc();

            foreach ($values as $key => $value) {
            var_dump(microtime());
                $attribute = $this->dba->cache(true)->where('name = ' . $key)->first();
                if (empty($attribute)) {
                    $attribute = $this->dba->make(array('name' => $key))->store();
                }
                $tab = array(
                    'entity_name'   => $this->entity,
                    'value'         => $value,
                    'entity'        => $entity->getId(),
                    'attribute'     => $attribute->getId()
                );
                if (true === $isNew) {
                    $relation = $this->dbr->make($tab)->store();
                    $object->id = $entity->id;
                } else {
                    $relation = $this->dbr
                    ->where('entity = ' . $entity->getId())
                    ->where('attribute = ' . $attribute->getId())
                    ->first();
                    if (!empty($relation)) {
                        $relation->setValue($value)->store();
                    } else {
                        $relation = $this->dbr->make($tab)->store();
                    }
                }
            }
            var_dump(microtime());
            return $object;
        }

        public function fetch($relations = array())
        {
            if (empty($relations)) {
                $relations  = $this->dbr
                ->where('entity_name = ' . $this->entity)
                ->order('attribute')
                ->fetch();
            }
            $collection = array();
            $records    = array();
            foreach ($relations as $relation) {
                if (!Arrays::exists($relation->getEntity(), $records)) {
                    $records[$relation->getEntity()] = array();
                }
                $att = $relation->getAttribute();
                $records[$relation->getEntity()][$this->dba->find($att)->getName()] = $relation->getValue();
            }
            $tuples = array();
            foreach ($records as $id => $record) {
                $check = $record;
                if (Arrays::exists('date_create', $check)) {
                    unset($check['date_create']);
                }
                if (Arrays::exists('date_modify', $check)) {
                    unset($check['date_modify']);
                }
                $checkTuple = sha1(serialize($check));
                if (!Arrays::in($checkTuple, $tuples)) {
                    $record['id'] = $id;
                    array_push($collection, $this->make($record));
                    array_push($tuples, $checkTuple);
                } else {
                    $entityTuple = $this->dbe->find($id);
                    $this->dbe->delete($entityTuple);
                    $relations = $this->dbr->where('entity = ' . $id)->fetch();
                    foreach ($relations as $relation) {
                        $this->dbr->delete($relation);
                    }
                }
            }
            return $collection;
        }

        private function makeData($res)
        {
            $data = array();
            foreach ($res as $entity) {
                $relations = $this->dbr
                ->where('entity = ' . $entity)
                ->fetch();
                $data = array_merge($data, $this->fetch($relations));
            }
            return $data;
        }

        public function findBy($field, $value, $one = false)
        {
            $data = $this->makeData($this->query("$field = $value"));

            if (true === $one) {
                if (!empty($data)) {
                    return Arrays::first($data);
                }
            }
            return $data;
        }

        public function reset()
        {
            $this->wheres   = array();
            $this->results  = array();
            return $this;
        }

        public function results()
        {
            $data = $this->makeData($this->results);
            $this->reset();
            return $data;
        }

        public function first()
        {
            $obj = null;
            $data =  $this->makeData($this->results);
            if (!empty($data)) {
                $obj = Arrays::first($data);
            }
            $this->reset();
            return $obj;
        }

        public function last()
        {
            $obj = null;
            $data =  $this->makeData($this->results);
            if (!empty($data)) {
                $obj = Arrays::last($data);
            }
            $this->reset();
            return $obj;
        }

        public function order($fieldOrder = 'date_create', $orderDirection = 'ASC', $results = array())
        {
            $res = count($results) ? $results : $this->currrentRes();
            if (empty($res)) return $this;

            $data = $this->makeData($res);

            $fields = array_keys(Arrays::first($data)->assoc());

            $sort = array();
            foreach($data as $objectCreated) {
                foreach ($fields as $key) {
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

        public function where($condition, $op = 'AND')
        {
            $res = $this->query($condition);
            if (empty($this->wheres)) {
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

        public function find($id)
        {
            $relations = $this->dbr
            ->where('entity = ' . $id)
            ->fetch();
            $data = $this->fetch($relations);
            $obj = null;
            if (!empty($data)) {
                $obj = Arrays::first($data);
            }
            return $obj;
        }

        private function currrentRes()
        {
            if (!empty($this->results)) {
                return $this->results;
            }
            $relations  = $this->dbr
            ->where('entity_name = ' . $this->entity)
            ->groupBy('entity')
            ->fetch();

            $collection = array();
            foreach ($relations as $relation) {
                $entity = $relation->getEntity();
                if (!Arrays::in($entity, $collection)) {
                    array_push($collection, $entity);
                }
            }

            return $collection;
        }

        public function groupBy($field, $results = array())
        {
            $res = count($results) ? $results : $this->currrentRes();
            if (count($res)) {
                $data       = $this->makeData($res);
                $groupBys   = array();
                $ever       = array();
                foreach ($data as $key => $object) {
                    $getter = getter($field);
                    $obj    = $object->$getter();
                    if (!Arrays::in($obj, $ever)) {
                        $groupBys[$key] = $object->id;
                        $ever[]         = $obj;
                    }
                }
                $this->results = $groupBys;
                $this->order($field);
            }
            return $this;
        }

        public function limit($limit, $offset = 0, $results = array())
        {
            $res = count($results) ? $results : $this->currrentRes();
            $this->results = array_slice($res, $offset, $limit);
            return $this;
        }

        public function sum($field, $results = array())
        {
            $res = count($results) ? $results : $this->currrentRes();
            $sum = 0;

            if (count($res)) {
                $data = $this->makeData($res);
                foreach ($data as $object) {
                    $getter = getter($field);
                    $sum += $object->$getter();
                }
            }
            $this->reset();
            return $sum;
        }

        public function avg($field, $results = array())
        {
            $res = count($results) ? $results : $this->currrentRes();
            $sum = 0;
            if (count($res)) {
                $data = $this->makeData($res);
                foreach ($data as $object) {
                    $getter = getter($field);
                    $sum += $object->$getter();
                }
            }
            $this->reset();
            return ($sum / count($res));
        }

        public function min($field, $results = array())
        {
            $res = count($results) ? $results : $this->currrentRes();
            $min = 0;
            if (count($res)) {
                $first = true;
                $data = $this->makeData($res);
                foreach ($data as $object) {
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
            $res = count($results) ? $results : $this->currrentRes();
            $max = 0;
            if (count($res)) {
                $first = true;
                $data = $this->makeData($res);
                foreach ($data as $object) {
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

        public function query($condition)
        {
            list($field, $op, $value) = explode(' ', $condition, 3);
            $collection = array();
            $attribute = $this->dba->where('name = ' . $field)->first();
            $results = $this->dbr
            ->where('attribute = ' . $attribute->getId())
            ->where('entity_name = ' . $this->entity)
            ->where('value ' . $op . ' ' . $value)
            ->fetch();
            foreach ($results as $result) {
                if (!Arrays::in($result->getEntity(), $collection)) {
                    array_push($collection, $result->getEntity());
                }
            }
            return $collection;
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
            return $this->entity;
        }

        public function cache($bool)
        {
            $this->cache = $bool;
            return $this;
        }

        public function ttl($ttl = 3600)
        {
            $this->ttl = $ttl;
            return $this;
        }
    }
