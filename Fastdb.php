<?php
    namespace Thin;

    class Fastdb
    {
        private $ns, $entity, $db, $key, $size;

        public function __construct($ns, $entity, $size = 10000000)
        {
            $this->ns       = $ns;
            $this->entity   = $entity;
            $this->size     = $size;
            $this->db       = $this->connect($ns, $entity);
            if ($ns != 'core' && $entity != 'expirate') {
                $this->clean();
            }
        }

        public function __destruct()
        {
            if ($this->db) {
                $last   = STORAGE_PATH . DS . 'memory' . sha1($this->ns . $this->entity) . '.last';
                $size   = STORAGE_PATH . DS . 'memory' . sha1($this->ns . $this->entity) . '.dbsize';
                $age    = time() - filemtime($last);

                if ($age >= 900) {
                    File::delete($last);
                    File::create($last);
                    $this->write();
                }
                File::delete($size);
                File::put($size, shmop_size($this->db));
                shmop_close($this->db);
            }
        }

        private function write($data = null)
        {
            if (is_null($data)) {
                $db = STORAGE_PATH . DS . 'memory' . sha1($this->ns . $this->entity) . '.db';
                $data = $this->data();
                File::delete($db);
                File::put($db, $data);
            }
            shmop_delete($this->db);
            shmop_close($this->db);
            $size = $this->size > strlen($data) ? $this->size : strlen($data);
            $this->size = $size;
            $this->db = shmop_open($this->key, 'c', 0755, $size);
            return shmop_write($this->db, $data, 0);
        }

        public function data()
        {
            return shmop_read($this->db, 0, 0);
        }

        public function keys($pattern = 'all')
        {
            $data = $this->data();
            $data = !strstr($data, '{') ? array() : json_decode($data, true);
            $keys = array_keys($data);
            if ($pattern == 'all') {
                return $keys;
            }
            $pattern = repl('*', '', $pattern);
            $collection = array();
            if (count($keys)) {
                foreach ($keys as $key) {
                    if (strstr($key, $pattern)) {
                        array_push($collection, $key);
                    }
                }
            }
            return $collection;
        }

        public function set($key, $value)
        {
            $data = $this->data();
            $data = !strstr($data, '{') ? array() : json_decode($data, true);

            $data[$key] = $value;
            $this->write(json_encode($data));
            return $this;
        }

        public function get($key)
        {
            $data = $this->data();
            $data = !strstr($data, '{') ? array() : json_decode($data, true);
            $value = isAke($data, $key, null);
            return !strlen($value) ? null : $value;
        }

        public function delete($key)
        {
            return $this->del($key);
        }

        public function del($key)
        {
            $data = $this->data();
            $data = !strstr($data, '{') ? array() : json_decode($data, true);
            $value = isAke($data, $key, null);
            if (strlen($value)) {
                unset($data[$key]);
                $this->write(json_encode($data));
            }
            return $this;
        }

        public function incr($by = 1)
        {
            $ns = 'core';
            $entity = 'count';
            $db = Fastdb::instance($ns, $entity);
            $key = $this->ns . '_' . $this->entity;
            $val = $db->get($key);
            if (!strlen($val)) {
                $val = 1;
            } else {
                $val = (int) $val;
                $val += $by;
            }
            $db->set($key, $val);
            return $val;
        }

        public function decr($by = 1)
        {
            $ns = 'core';
            $entity = 'count';
            $db = Fastdb::instance($ns, $entity);
            $key = $this->ns . '_' . $this->entity;
            $val = $db->get($key);
            if (!strlen($val)) {
                $val = 0;
            } else {
                $val = (int) $val;
                $val -= $by;
                $val = 0 > $val ? 0 : $val;
            }
            $db->set($key, $val);
            return $val;
        }

        public function search(array $args)
        {
            $collection = array();
            if (count($args)) {
                $data = $this->data();
                $data = !strstr($data, '{') ? array() : json_decode($data, true);
                if (count($data)) {
                    $fields = array_keys($args);
                    $values = array_values($args);
                    foreach ($data as $key => $row) {
                        $add = true;
                        foreach ($fields as $index => $field) {
                            $val = $values[$index];
                            $value = isAke($row, $field, null);
                            if ($value != $val) {
                                $add = false;
                            }
                        }
                        if (true == $add) {
                            array_push($collection, $key);
                        }
                    }
                }
            }
            return $collection;
        }

        public function expire($key, $ttl = 3600)
        {
            $val = $this->get($key);
            if (strlen($val)) {
                $db = Fastdb::instance('core', 'expirate');
                $record = array();
                $record['key']      = $key;
                $record['ns']       = $this->ns;
                $record['entity']   = $this->entity;
                $record['expire']   = time() + $ttl;
                $exists = $db->search(array(
                    'key' => $key,
                    'ns' => $this->ns,
                    'entity' => $this->entity
                ));
                $id = count($exists) ? Arrays::first($exists) : $db->incr('core::expire::count');
                $db->set($id, $record);
            }
        }

        private function clean()
        {
            $now = time();
            $db = Fastdb::instance('core', 'expirate');
            $data = $db->data();
            $data = !strstr($data, '{') ? array() : json_decode($data, true);
            if (count($data)) {
                foreach ($data as $key => $row) {
                    if ($row['expire'] <= $now) {
                        $dbRow = Fastdb::instance($row['ns'], $row['entity']);
                        $dbRow->del($row['key']);
                    }
                }
            }
        }

        private function connect($ns, $entity)
        {
            $filename   = STORAGE_PATH . DS . sha1($ns . $entity . 'memory');
            $db         = STORAGE_PATH . DS . 'memory' . sha1($ns . $entity) . '.db';
            $last       = STORAGE_PATH . DS . 'memory' . sha1($ns . $entity) . '.last';
            $sizeFile   = STORAGE_PATH . DS . 'memory' . sha1($this->ns . $this->entity) . '.dbsize';
            $mustLoad   = false;

            if (!File::exists($filename)) {
                $mustLoad = true;
                File::create($filename);
            }

            if (!File::exists($db)) {
                File::create($db);
            }

            if (!File::exists($last)) {
                File::create($last);
            }

            if (!File::exists($sizeFile)) {
                $size = $this->size;
                File::delete($sizeFile);
                File::put($sizeFile, $this->size);
            } else {
                $size = File::get($sizeFile);
            }

            for($key = array(); count($key) < strlen($filename); $key[] = ord(substr($filename, sizeof($key), 1)));
            $dbId = dechex(array_sum($key));
            $dbId = (int) $dbId;
            $dbId += 6;

            $id = shmop_open($dbId, "c", 0755, $size);
            $this->key = $dbId;
            return $id;
        }

        public static function instance($ns, $entity)
        {
            $key    = sha1(serialize(func_get_args()));
            $has    = Instance::has('Fastdb', $key);
            if (true === $has) {
                return Instance::get('Fastdb', $key);
            } else {
                return Instance::make('Fastdb', $key, with(new self($ns, $entity)));
            }
        }
    }
