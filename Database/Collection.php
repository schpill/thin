<?php
    namespace Thin\Database;

    use IteratorAggregate;
    use ArrayAccess;
    use Countable;
    use ArrayIterator;
    use Closure;
    use Thin\Database;
    use Thin\Container;
    use Thin\Arrays;

    class Collection  implements IteratorAggregate, ArrayAccess, Countable
    {

        private $_items = array();

        /**
         * Make a collection from a array of Model
         *
         * @param array $models models to add to the collection
         */
        public function __construct($models = array())
        {
            $items = array();
            $i = 0;
            if (count($models)) {
                foreach ($models as $model) {
                    if (!$model instanceof Container) {
                        continue;
                    }
                    if ($model->exists()) {
                        $id = (int) $model->id();
                    } else {
                        $id = $i++;
                        $model->setTempId($id);
                    }
                    $items[$id] = $model;
                }
            }
            $this->_items = $items;
        }

        /**
         * Add a model item or model array or ModelSet to this set
         *
         * @param mixed $items model item or arry or ModelSet to add
         *
         * @return $this
         */
        public function add($items)
        {
            if ($items && $items instanceof Container) {
                $id = (int) $items->id();
                $this->_items[$id] = $items;
            } elseif (Arrays::is($items)) {
                foreach ($items as $obj) {
                    if ($obj instanceof Container) {
                        $this->add($obj);
                    }
                }
            } elseif ($items instanceof self) {
                $this->add($items->toArray());
            }
            return $this;
        }

        /**
         * Get item by numeric index
         *
         * @param int $index model to get
         *
         * @return Model
         */
        public function get($index = 0)
        {
            if (is_integer($index)) {
                if ($index + 1 > $this->count()) {
                    return null;
                } else {
                    return Arrays::first(array_slice($this->_items, $index, 1));
                }
            } else {
                if ($this->has($index)) {
                    return $this->_items[$index];
                }
            }
            return null;
        }

        /**
         * Remove a record from the collection
         *
         * @param int|Model $param model to remove
         *
         * @return boolean
         */
        public function remove($param)
        {
            if ($param instanceof Container) {
                $param = $param->id();
            }

            $item = $this->get($param);
            if ($item) {
                $id = (int) $item->id();
                if ($this->_items[$id]) {
                    unset($this->_items[$id]);
                }
            }
            return $this;
        }

        /**
         * Slice the underlying collection array.
         *
         * @param int  $offset       offset to slice
         * @param int  $length       length
         * @param bool $preserveKeys preserve keys
         *
         * @return Collection
         */
        public function slice($offset, $length = null, $preserveKeys = false)
        {
            return new self(array_slice($this->_items, $offset, $length, $preserveKeys));
        }

        /**
         * Take the first or last {$limit} items.
         *
         * @param int $limit limit
         *
         * @return Collection
         */
        public function take($limit = null)
        {
            if ($limit < 0) return $this->slice($limit, abs($limit));
            return $this->slice(0, $limit);
        }

        /**
         * Determine if the collection is empty or not.
         *
         * @return bool
         */
        public function isEmpty()
        {
            return empty($this->_items);
        }

        /**
         * Determine if a record exists in the collection
         *
         * @param int|object $param param
         *
         * @return boolean
         */
        public function has($param)
        {
            if ($param instanceof Container) {
                $id = (int) $param->id();
            } elseif (is_integer($param)) {
                $id = $param;
            }
            if (isset($id) && isset($this->_items[$id])) {
                return true;
            }
            return false;
        }

        /**
         * Run a map over the collection using the given Closure
         *
         * @param Closure $callback callback
         *
         * @return Collection
         */
        public function map(Closure $callback)
        {
            $this->_items = array_map($callback, $this->_items);
            return $this;
        }

        /**
         * Filter the collection using the given Closure and return a new collection
         *
         * @param Closure $callback callback
         *
         * @return Collection
         */
        public function filter(Closure $callback)
        {
            return new self(array_filter($this->_items, $callback));
        }

        /**
         * Sort the collection using the given Closure
         *
         * @param Closure $callback callback
         * @param boolean $asc      asc
         *
         * @return Collection
         */
        public function sortBy(Closure $callback, $args = array(), $asc = false)
        {
            $results = array();

            foreach ($this->_items as $key => $value) {
                array_push($args, $value);
                $results[$key] = call_user_func_array($callback, $$args);
            }

            if (true === $asc) {
                asort($results);
            } else {
                arsort($results);
            }

            foreach (array_keys($results) as $key) {
                $results[$key] = $this->_items[$key];
            }

            $this->_items = $results;
            return $this;
        }

        /**
         * Reverse items order.
         *
         * @return Collection
         */
        public function reverse()
        {
            $this->_items = array_reverse($this->_items);
            return $this;
        }

        /**
         * Make a collection from a array of Model
         *
         * @param array $models models
         *
         * @return Collection
         */
        public static function make($models)
        {
            return new self($models);
        }

        /**
         * First item
         *
         * @return Model
         */
        public function first()
        {
            return Arrays::first($this->_items);
        }

        /**
         * Last item
         *
         * @return Model
         */
        public function last()
        {
            return Arrays::last($this->_items);
        }

        public function items($array = false)
        {
            return true === $array ? $this->toArray(false, $array) : $this->_items;
        }

        public function rows($array = false)
        {
            return $this->items($array);
        }

        /**
         * Execute a callback over each item.
         *
         * @param \Closure $callback callback
         *
         * @return Collection
         */
        public function each(Closure $callback)
        {
            array_map($callback, $this->_items);
            return $this;
        }

        /**
         * Count items
         *
         * @return int
         */
        public function count()
        {
            return count($this->_items);
        }

        /**
         * Export all items to a Array
         *
         * @param boolean $is_numeric_index is numeric index
         * @param boolean $itemToArray      item to array
         *
         * @return array
         */
        public function toArray($isNumericIndex = true, $itemToArray = false)
        {

            $array = array();
            foreach ($this->_items as $item) {
                if (false === $isNumericIndex) {
                    $id = (int) $item->id();
                    if (true === $itemToArray) {
                        $item = $item->assoc();
                    }
                    $array[$id] = $item;
                } else {
                    if (true === $itemToArray) {
                        $item = $item->assoc();
                    }
                    $array[] = $item;
                }
            }
            return $array;
        }


        /**
         * Export all items to a json string
         *
         * @param boolean $is_numeric_index is numeric index
         * @param boolean $itemToArray      item to array
         *
         * @return string
         */
        public function toJson($render = false)
        {
            $json = json_encode($this->toArray(true, true));
            if (false === $render) {
                return $json;
            } else {
                header('content-type: application/json; charset=utf-8');
                die($json);
            }
        }

        /**
         *
         * @return array
         */
        public function toEmbedsArray()
        {
            $array = array();
            foreach ($this->_items as $item) {
                $item = $item->assoc();
                $array[] = $item;
            }
            return $array;
        }

        /**
         * get iterator
         *
         * @return ArrayIterator
         */
        public function getIterator()
        {
            return new ArrayIterator($this->_items);
        }

        /**
         * Offset exists
         *
         * @param int|string $key index
         *
         * @return boolean
         */
        public function offsetExists($key)
        {
            if (is_integer($key) && $key + 1 <= $this->count()) {
                return true;
            }
            return $this->has($key);
        }

        /**
         * Offset get
         *
         * @param int|string $key index
         *
         * @return boolean
         */
        public function offsetGet($key)
        {
            return $this->get($key);
        }

        /**
         * Offset set
         *
         * @param mixed $offset offset
         * @param mixed $value  value
         *
         * @throws \Exception
         *
         * @return null
         */
        public function offsetSet($offset, $value)
        {
            throw new \Exception('cannot change the set by using []');
        }

        /**
         * Offset unset
         *
         * @param int $index index
         *
         * @return bool
         */
        public function offsetUnset($index)
        {
            $this->remove($index);
        }

        /**
         * Save items
         *
         * @return Collection
         */
        public function save()
        {
            if (count($this->_items)) {
                foreach($this->_items as $item) {
                    if(true === $item->exists()) {
                        $item->save();
                    }
                }
            }
            return $this;
        }

        /**
         * Delete items
         *
         * @return Collection
         */
        public function delete()
        {
            if (count($this->_items)) {
                foreach($this->_items as $key => $item) {
                    if(true === $item->exists()) {
                        $deleted = $item->delete();
                        unset($this->_items[$key]);
                    }
                }
            }
            return $this;
        }

        // public function where($condition, $op = 'AND')
        // {
        //     if (count($this->_items)) {
        //         $db = $this->first()->orm();
        //         return $db->where($db->database . '.' . $db->table . '.id IN (' . implode(',', $this->getIds()) . ')')->where($condition, $op);
        //     }
        //     return $this;
        // }

        private function getIds()
        {
            $rows = $this->rows();
            $ids = array();
            foreach ($rows as $row) {
                array_push($ids, $row->id());
            }
            return $ids;
        }

        public function __call($method, $args)
        {
            if (count($this->_items)) {
                $first = $this->first();
                $key = sha1('orm' . $first->_token);
                $orm = isAke($first->values, $key, false);
                if (false !== $orm) {
                    $db = $first->orm();
                    $methods = get_class_methods($db);
                    if (Arrays::in($method, $methods)) {
                        $instance = $db->where($db->database . '.' . $db->table . '.id IN (' . implode(',', $this->getIds()) . ')');
                        return call_user_func_array(array($instance, $method), $args);
                    }
                }
            }
        }
    }
