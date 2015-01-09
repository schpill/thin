<?php
    namespace Thin;

    use Closure;
    use Dbredis\Db;

    class Option
    {
        private $db, $id, $optMotor, $optDb, $optTable, $results;
        private $wheres = [];

        public function __construct($type, $model, $inCache = true)
        {
            if (!is_object($model)) {
                throw new Exception("The first argument is not a model.");
            }

            if ($model instanceof Container) {
                $motor = 'dbjson';
            } else {
                $motor = 'dbredis';
            }

            $this->db       = Db::instance(SITE_NAME, $type)->inCache($inCache);
            $this->optDb    = $model->db()->db;
            $this->optTable = $model->db()->table;
            $this->optMotor = $motor;
            $this->id       = $model->id;
        }

        public function sets($key, $value)
        {
            return $this->set($key, $value, true);
        }

        public function setsMultiple(array $options)
        {
            foreach ($options as $k => $v) {
                $this->set($k, $v, true);
            }

            return $this;
        }

        public function set($name, $value, $multiple = false)
        {
            if (true === $multiple) {
                $this->db->firstOrCreate([
                    'object_id'         => $this->id,
                    'object_motor'      => $this->optMotor,
                    'object_database'   => $this->optDb,
                    'object_table'      => $this->optTable,
                    'name'              => $name,
                    'value'             => $value
                ]);
            } else {
                $this->db->firstOrCreate([
                    'object_id'         => $this->id,
                    'object_motor'      => $this->optMotor,
                    'object_database'   => $this->optDb,
                    'object_table'      => $this->optTable,
                    'name'              => $name
                ])->setValue($value)->save();
            }

            return $this;
        }

        public function setMultiple($name, $value)
        {
            return $this->set($name, $value, true);
        }

        public function has($name, $value = null)
        {
            $query = $this->db
            ->where(['object_motor', '=', $this->optMotor])
            ->where(['object_database', '=', $this->optDb])
            ->where(['object_table', '=', $this->optTable])
            ->where(['object_id', '=', $this->id])
            ->where(['name', '=', $name]);

            if (!empty($value)) {
                $query = $query->where(['value', '=', $value]);
            }

            $options = $query->exec();

            return !empty($options);
        }

        public function get($name, $id = false, $default = null)
        {
            $option = $this->db
            ->where(['object_motor', '=', $this->optMotor])
            ->where(['object_database', '=', $this->optDb])
            ->where(['object_table', '=', $this->optTable])
            ->where(['object_id', '=', $this->id])
            ->where(['name', '=', $name])
            ->first(true);

            return !$option ? $default : !$id ? $option->value : [$option->id => $option->value];
        }

        public function gets($name, $id = false)
        {
            return $this->getMultiple($name, $id);
        }

        public function del($what)
        {
            /* polymorphism */

            if (is_numeric($what)) {
                $option = $this->db->find($id);
            } elseif (is_object($what)) {
                return $what->delete();
            } else {
                $option = $this->db
                ->where(['object_motor', '=', $this->optMotor])
                ->where(['object_database', '=', $this->optDb])
                ->where(['object_table', '=', $this->optTable])
                ->where(['object_id', '=', $this->id])
                ->where(['name', '=', $name])
                ->first(true);
            }

            return !$option ? false : $option->delete();
        }

        public function dels($name)
        {
            $options = $this->db
            ->where(['object_motor', '=', $this->optMotor])
            ->where(['object_database', '=', $this->optDb])
            ->where(['object_table', '=', $this->optTable])
            ->where(['object_id', '=', $this->id])
            ->where(['name', '=', $name])
            ->exec(true);

            foreach ($options as $option) {
                $option->delete();
            }

            return true;
        }

        public function delByValue($name, $value)
        {
            $options = $this->db
            ->where(['object_motor', '=', $this->optMotor])
            ->where(['object_database', '=', $this->optDb])
            ->where(['object_table', '=', $this->optTable])
            ->where(['object_id', '=', $this->id])
            ->where(['name', '=', $name])
            ->where(['value', '=', $value])
            ->exec(true);

            foreach ($options as $option) {
                $option->delete();
            }

            return true;
        }

        public function getMultiple($name, $id = false)
        {
            $collection = [];

            $options = $this->db
            ->where(['object_motor', '=', $this->optMotor])
            ->where(['object_database', '=', $this->optDb])
            ->where(['object_table', '=', $this->optTable])
            ->where(['object_id', '=', $this->id])
            ->where(['name', '=', $name])
            ->exec();

            foreach ($options as $option) {
                if (false === $id) {
                    array_push($collection, $option['value']);
                } else {
                    array_push($collection, [$option['id'] => $option['value']]);
                }
            }

            return $collection;
        }

        public function all()
        {
            $collection = [];

            $options = $this->db
            ->where(['object_motor', '=', $this->optMotor])
            ->where(['object_database', '=', $this->optDb])
            ->where(['object_table', '=', $this->optTable])
            ->where(['object_id', '=', $this->id])
            ->exec();

            foreach ($options as $option) {
                if (!isset($collection[$option['name']])) {
                    $collection[$option['name']] = $option['value'];
                } else {
                    /* cas des options à valeurs multiples */
                    if (!Arrays::is($collection[$option['name']])) {
                        $val = $collection[$option['name']];
                        unset($collection[$option['name']]);

                        $collection[$option['name']] = [];
                        $collection[$option['name']][] = $val;
                        $collection[$option['name']][] = $option['value'];
                    } else {
                        $collection[$option['name']][] = $option['value'];
                    }
                }
            }
            return $collection;
        }

        public function inCache($bool = true)
        {
            $this->db = $this->db->inCache($bool);

            return $this;
        }

        public function custom(Closure $query, $object = false)
        {
            $collection = [];

            $options = $this->all();

            if (!empty($options)) {
                foreach ($option as $o) {
                    $check = call_user_func_array($query, [$o]);

                    if (true === $check) {
                        $o = $object ? $this->row($o['name'], $o['value']) : [$o['name'] => $o['value']];
                        array_push($collection, $o);
                    }
                }
            }

            return $object ? new Database\Collection($collection) : $collection;
        }

        public function row($name, $value)
        {
            $setter = setter($name);

            return with(new Container)->$setter($value);
        }

        private function intersect($tab1, $tab2)
        {
            $ids1       = [];
            $ids2       = [];
            $collection = [];

            foreach ($tab1 as $row) {
                $id = isAke($row, 'id', null);

                if (strlen($id)) {
                    array_push($ids1, $id);
                }
            }

            foreach ($tab2 as $row) {
                $id = isAke($row, 'id', null);

                if (strlen($id)) {
                    array_push($ids2, $id);
                }
            }

            $sect = array_intersect($ids1, $ids2);

            if (!empty($sect)) {
                foreach ($sect as $idRow) {
                    array_push($collection, $this->find($idRow, false));
                }
            }

            return $collection;
        }

        public function where($condition, $op = 'AND', $results = [])
        {
            $res = $this->search($condition, $results, false);

            if (empty($this->wheres)) {
                $this->results = array_values($res);
            } else {
                $values = array_values($this->results);

                switch ($op) {
                    case 'AND':
                        $this->results = $this->intersect($values, array_values($res));
                        break;
                    case 'OR':
                        $this->results = $values + $res;
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

        private function search($condition = null, $results = [], $populate = true)
        {
            if (empty($condition)) {
                $datas = empty($results) ? $this->all() : $results;

                if (true === $populate) {
                    $this->results = $datas;
                }

                return $datas;
            }

            if (!Arrays::is($condition)) {
                $condition  = str_replace(
                    [' LIKE START ', ' LIKE END ', ' NOT LIKE ', ' NOT IN '],
                    [' LIKESTART ', ' LIKEEND ', ' NOTLIKE ', ' NOTIN '],
                    $condition
                );

                if (fnmatch('* = *', $condition)) {
                    list($field, $value) = explode(' = ', $condition, 2);
                    $op = '=';
                } elseif (fnmatch('* < *', $condition)) {
                    list($field, $value) = explode(' < ', $condition, 2);
                    $op = '<';
                } elseif (fnmatch('* > *', $condition)) {
                    list($field, $value) = explode(' > ', $condition, 2);
                    $op = '>';
                } elseif (fnmatch('* <= *', $condition)) {
                    list($field, $value) = explode(' <= ', $condition, 2);
                    $op = '<=';
                } elseif (fnmatch('* >= *', $condition)) {
                    list($field, $value) = explode(' >= ', $condition, 2);
                    $op = '>=';
                } elseif (fnmatch('* LIKESTART *', $condition)) {
                    list($field, $value) = explode(' LIKESTART ', $condition, 2);
                    $op = 'LIKESTART';
                } elseif (fnmatch('* LIKEEND *', $condition)) {
                    list($field, $value) = explode(' LIKEEND ', $condition, 2);
                    $op = 'LIKEEND';
                } elseif (fnmatch('* NOTLIKE *', $condition)) {
                    list($field, $value) = explode(' NOTLIKE ', $condition, 2);
                    $op = 'NOTLIKE';
                } elseif (fnmatch('* LIKE *', $condition)) {
                    list($field, $value) = explode(' LIKE ', $condition, 2);
                    $op = 'LIKE';
                } elseif (fnmatch('* IN *', $condition)) {
                    list($field, $value) = explode(' IN ', $condition, 2);
                    $op = 'IN';
                } elseif (fnmatch('* NOTIN *', $condition)) {
                    list($field, $value) = explode(' NOTIN ', $condition, 2);
                    $op = 'NOTIN';
                } elseif (fnmatch('* != *', $condition)) {
                    list($field, $value) = explode(' != ', $condition, 2);
                    $op = '!=';
                } elseif (fnmatch('* <> *', $condition)) {
                    list($field, $value) = explode(' <> ', $condition, 2);
                    $op = '<>';
                }
            } else {
                list($field, $op, $value) = $condition;

                $op = str_replace(' ', '', $op);
            }

            $collection = [];

            $datas = empty($results) ? $this->all() : $results;

            if (fnmatch('*/*/*', $value)) {
                list($d, $m, $y) = explode('/', $value, 3);
                $value = mktime(23, 59, 59, $m, $d, $y);
            }

            if (!empty($datas)) {
                foreach ($datas as $tab) {
                    $old = $tab;
                    $new = [];
                    $new[$tab['name']] = $tab['value'];
                    $tab = $new;

                    if (!empty($tab)) {
                        $val = isAke($tab, $field, null);

                        if (Arrays::is($val)) {
                            if ($op != '!=' && $op != '<>' && !fnmatch('*NOT*', $op)) {
                                $check = Arrays::in($value, $val);
                            } else {
                                $check = !Arrays::in($value, $val);
                            }
                        } else {
                            if (strlen($val)) {
                                if ($value == 'null') {
                                    $check = false;

                                    if ($op == 'IS' || $op == '=') {
                                        $check = false;
                                    } elseif ($op == 'ISNOT' || $op == '!=' || $op == '<>') {
                                        $check = true;
                                    }
                                } else {
                                    $val    = str_replace('|', ' ', $val);
                                    $check  = $this->compare($val, $op, $value);
                                }
                            } else {
                                $check = false;

                                if ($value == 'null') {
                                    if ($op == 'IS' || $op == '=') {
                                        $check = true;
                                    } elseif ($op == 'ISNOT' || $op == '!=' || $op == '<>') {
                                        $check = false;
                                    }
                                }
                            }
                        }

                        if (true === $check) {
                            array_push($collection, $old);
                        }
                    }
                }
            }

            if (true === $populate) {
                $this->results = $collection;
            }

            return $collection;
        }

        private function compare($comp, $op, $value)
        {
            $res = false;

            if (strlen($comp) && strlen($op) && strlen($value)) {
                $comp   = Inflector::lower(Inflector::unaccent($comp));
                $value  = Inflector::lower(Inflector::unaccent($value));

                switch ($op) {
                    case '=':
                        $res = sha1($comp) == sha1($value);
                        break;

                    case '>=':
                        $res = $comp >= $value;
                        break;

                    case '>':
                        $res = $comp > $value;
                        break;

                    case '<':
                        $res = $comp < $value;
                        break;

                    case '<=':
                        $res = $comp <= $value;
                        break;

                    case '<>':
                    case '!=':
                        $res = sha1($comp) != sha1($value);
                        break;

                    case 'LIKE':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        $res    = fnmatch($value, $comp);

                        break;

                    case 'NOTLIKE':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        $check  = fnmatch($value, $comp);

                        $res    = !$check;

                        break;

                    case 'LIKESTART':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '', $value);
                        $res    = (substr($comp, 0, strlen($value)) === $value);

                        break;

                    case 'LIKEEND':
                        $value = str_replace("'", '', $value);
                        $value = str_replace('%', '', $value);

                        if (!strlen($comp)) {
                            $res = true;
                        }

                        $res = (substr($comp, -strlen($value)) === $value);

                        break;

                    case 'IN':
                        $value      = str_replace('(', '', $value);
                        $value      = str_replace(')', '', $value);
                        $tabValues  = explode(',', $value);
                        $res        = Arrays::in($comp, $tabValues);

                        break;

                    case 'NOTIN':
                        $value      = str_replace('(', '', $value);
                        $value      = str_replace(')', '', $value);
                        $tabValues  = explode(',', $value);
                        $res        = !Arrays::in($comp, $tabValues);

                        break;
                }
            }

            return $res;
        }

        public function groupBy($field, $results = [])
        {
            $res = !empty($results) ? $results : $this->results;

            $groupBys   = [];
            $ever       = [];

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

        public function sort(Closure $sortFunc, $results = [])
        {
            $res = !empty($results) ? $results : $this->results;

            if (empty($res)) {
                return $this;
            }

            usort($res, $sortFunc);

            $this->results = $res;

            return $this;
        }

        public function order($fieldOrder, $orderDirection = 'ASC', $results = [])
        {
            $res = !empty($results) ? $results : $this->results;

            if (empty($res)) {
                return $this;
            }

            $sortFunc = function($key, $direction) {
                return function ($a, $b) use ($key, $direction) {
                    if (!isset($a[$key]) || !isset($b[$key])) {
                        return false;
                    }

                    if ('ASC' == $direction) {
                        return $a[$key] > $b[$key];
                    } else {
                        return $a[$key] < $b[$key];
                    }
                };
            };

            if (Arrays::is($fieldOrder) && !Arrays::is($orderDirection)) {
                $t = [];

                foreach ($fieldOrder as $tmpField) {
                    array_push($t, $orderDirection);
                }

                $orderDirection = $t;
            }

            if (!Arrays::is($fieldOrder) && Arrays::is($orderDirection)) {
                $orderDirection = Arrays::first($orderDirection);
            }

            if (Arrays::is($fieldOrder) && Arrays::is($orderDirection)) {
                for ($i = 0; $i < count($fieldOrder); $i++) {
                    usort($res, $sortFunc($fieldOrder[$i], $orderDirection[$i]));
                }
            } else {
                usort($res, $sortFunc($fieldOrder, $orderDirection));
            }

            $this->results = $res;

            return $this;
        }

        public function andWhere($condition, $results = [])
        {
            return $this->where($condition, 'AND', $results);
        }

        public function orWhere($condition, $results = [])
        {
            return $this->where($condition, 'OR', $results);
        }

        public function xorWhere($condition, $results = [])
        {
            return $this->where($condition, 'XOR', $results);
        }

        public function _and($condition, $results = [])
        {
            return $this->where($condition, 'AND', $results);
        }

        public function _or($condition, $results = [])
        {
            return $this->where($condition, 'OR', $results);
        }

        public function _xor($condition, $results = [])
        {
            return $this->where($condition, 'XOR', $results);
        }

        public function whereAnd($condition, $results = [])
        {
            return $this->where($condition, 'AND', $results);
        }

        public function whereOr($condition, $results = [])
        {
            return $this->where($condition, 'OR', $results);
        }

        public function whereXor($condition, $results = [])
        {
            return $this->where($condition, 'XOR', $results);
        }

        public function between($field, $min, $max, $object = false)
        {
            return $this->where([$field, '>=', $min])->where([$field, '<=', $max]);

            return $this;
        }

        public function limit($limit, $offset = 0, $results = [])
        {
            $res            = !empty($results) ? $results : $this->results;
            $offset         = count($res) < $offset ? count($res) : $offset;
            $this->results  = array_slice($res, $offset, $limit);

            return $this;
        }

        public function sum($field, $results = [])
        {
            $res = !empty($results) ? $results : $this->results;
            $sum = 0;

            if (!empty($res)) {
                foreach ($res as $id => $tab) {
                    $val = isAke($tab, $field, 0);
                    $sum += $val;
                }
            }

            $this->reset();

            return (int) $sum;
        }

        public function avg($field, $results = [])
        {
            $res = !empty($results) ? $results : $this->results;

            return (float) $this->sum($field, $res) / count($res);
        }

        public function min($field, $results = [])
        {
            $res = !empty($results) ? $results : $this->results;
            $min = 0;

            if (!empty($res)) {
                $first = true;

                foreach ($res as $id => $tab) {
                    $val = isAke($tab, $field, 0);

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

        public function max($field, $results = [])
        {
            $res = !empty($results) ? $results : $this->results;
            $max = 0;

            if (!empty($res)) {
                $first = true;

                foreach ($res as $id => $tab) {
                    $val = isAke($tab, $field, 0);

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

        public function rand($results = [])
        {
            $res = !empty($results) ? $results : $this->results;
            shuffle($res);
            $this->results = $res;

            return $this;
        }

        public function exec($object = false)
        {
            $collection = [];

            if (!empty($this->results)) {
                foreach ($this->results as $o) {
                    $o = true === $object ? $this->row($o['name'], $o['value']) : [$o['name'] => $o['value']];
                    array_push($collection, $o);
                }
            }

            $this->reset();

            return true === $object ? new Database\Collection($collection) : $collection;
        }

        public function reset()
        {
            $this->results      = null;
            $this->wheres       = [];

            return $this;
        }
    }
