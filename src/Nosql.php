<?php
    namespace Thin;

    class Nosql
    {
        private $dir, $file, $data;

        public function __construct($db, $collection)
        {
            $this->dir = STORAGE_PATH . DS . $db;

            if (!is_dir($this->dir)) {
                mkdir($this->dir, 0755, true);
            }

            if (!is_dir($this->dir)) {
                throw new Exception("The directory $this->dir can not be created. Please, check write permission on " . STORAGE_PATH);
            }

            $this->file = $this->dir . DS . $collection . '.nosql';

            if (!is_writable($this->file)) {
                $this->write();
            }

            if (!is_writable($this->file)) {
                throw new Exception("The database $this->file can not be created. Please, check write permission on " . STORAGE_PATH);
            }
            $this->read();
        }

        public function find($id)
        {
            if (count($this->data) < 1) {
                return false;
            }
            foreach ($this->data as $row) {
                if (array_key_exists('id', $row)) {
                    if ($row['id'] == $id) {
                        return $this->row($row);
                    }
                }
            }
            return false;
        }

        public function findOneBy($field, $value)
        {
            return $this->findBy($field, $value, true);
        }

        public function findBy($field, $value, $one = false)
        {
            if (count($this->data) < 1) {
                return false;
            }
            $collection = array();
            foreach ($this->data as $row) {
                if (array_key_exists($field, $row)) {
                    if ($row[$field] == $value) {
                        if (true === $one) {
                            return $this->row($row);
                        } else {
                            array_push($collection, $this->row($row));
                        }
                    }
                }
            }
            return $collection;
        }

        public function all()
        {
            return $this->read();
        }

        public function save(Container $row)
        {
            $id = isAke($row->toArray(), 'id', null);
            return is_null($id) ? $this->add($row) : $this->insertRow($this->toArray($row));
        }

        private function toArray(Container $row)
        {
            $tab = array();
            $row = $row->toArray();
            foreach ($row as $k => $v) {
                if (!is_callable($v)) {
                    $tab[$k] = $v;
                }
            }
            return $tab;
        }

        private function add(Container $row)
        {
            $id         = $this->id();
            $tab        = $this->toArray($row);
            $tab['id']  = $id;
            return $this->insertRow($tab);
        }

        private function insertRow($tab)
        {
            if (count($this->data)) {
                $new = array();
                $this->data = $this->clean($tab['id']);
            }
            array_push($this->data, $tab);
            $this->write($this->data);
            return $this->row($tab);
        }

        public function delete($id)
        {
            if (count($this->data)) {
                $this->data = $this->clean($id);
                $this->write($this->data);
                return true;
            }
            return false;
        }

        private function clean($id)
        {
            $new = array();
            foreach ($this->data as $row) {
                if (array_key_exists('id', $row)) {
                    if ($row['id'] != $id) {
                        array_push($new, $row);
                    }
                }
            }
            return $new;
        }

        public function count()
        {
            return count($this->data);
        }

        public function first()
        {
            return count($this->data) ? current($this->data) : null;
        }

        public function last()
        {
            return count($this->data) ? end($this->data) : null;
        }

        private function write($data = array(), $append = false)
        {
            $this->data = $data;
            return file_put_contents($this->file, json_encode($data), LOCK_EX | (true === $append ? FILE_APPEND : 0));
        }

        private function read()
        {
            $this->data = json_decode(file_get_contents($this->file), true);
            return $this->data;
        }

        private function fields($row)
        {
            if (is_object($row)) {
                if ($row instanceof Container) {
                    $row = $this->toArray($row);
                }
            }
            if (Arrays::is($row)) {
                return array_keys($row);
            }
            return array();
        }

        private function values($row)
        {
            if (is_object($row)) {
                if ($row instanceof Container) {
                    $row = $this->toArray($row);
                }
            }
            if (Arrays::is($row)) {
                return array_values($row);
            }
            return array();
        }

        public function create()
        {
            return $this->row(array());
        }

        private function row(array $row)
        {
            $object = new Container;
            $object->populate($row);
            $db = $this;

            $save = function() use ($object, $db) {
                return $db->save($object);
            };

            $delete = function() use ($object, $db) {
                return strlen($object->getId()) ? $db->delete($object->getId()) : false;
            };

            $hook = function ($name, $cb) use($object, $db) {
                $object->$name = function () use ($cb, $object, $db) {
                    return call_user_func_array($cb, func_get_args());
                };
                return $object;
            };

            $object->event('save', $save)
            ->event('delete', $delete)
            ->event('hook', $hook);

            return $object;
        }

        private function id()
        {
            $incr = str_replace('.nosql', '.index', $this->file);

            if (!is_writable($incr)) {
                file_put_contents($incr, '0', LOCK_EX | 0);
            }

            if (!is_writable($incr)) {
                throw new Exception("The index $incr can not be created. Please, check write permission on " . STORAGE_PATH);
            }

            $last = fgc($incr);
            $last = (int) $last;

            $next = $last + 1;
            file_put_contents($incr, $next, LOCK_EX | 0);
            return $next;
        }
    }
