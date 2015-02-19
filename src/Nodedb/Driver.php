<?php
    namespace Thin\Nodedb;
    use Thin\File;
    use Thin\Arrays;
    use Thin\Bucket;

    class Driver
    {
        private static $instances = array();
        private $db, $lock, $buffer;

        public function __construct($namespace, $entity)
        {
            $this->db = STORAGE_PATH . DS . 'dbNode_' . $namespace . '::' . $entity . '.data';
            $this->lock = STORAGE_PATH . DS . 'dbNode_' . $namespace . '::' . $entity . '.lock';
            if (!File::exists($this->db)) {
                File::put($this->db, json_encode(array()));
            }
            $this->buffer = json_decode(fgc($this->db), true);
            $this->clean();
        }

        public function trash()
        {
            File::delete($this->db);
            return $this;
        }

        public function _empty()
        {
            File::delete($this->db);
            File::put($this->db, json_encode(array()));
            return $this;
        }

        public static function instance($namespace, $entity)
        {
            $key = sha1($namespace . $entity);
            $instance = isAke(static::$instances, $key, null);
            if (is_null($instance)) {
                $instance = new self($namespace, $entity);
                static::$instances[$key] = $instance;
            }
            return $instance;
        }

        private function commit()
        {
            File::delete($this->db);
            File::put($this->db, json_encode($this->buffer));
            return $this;
        }

        public function keys($pattern)
        {
            $collection = array();
            if (strstr($pattern, '%%') or strstr($pattern, '*')) {
                $pattern = repl('%%', '', repl('*', '', $pattern));
            }
            return $this->search($pattern);
        }

        public function set($key, $value, $expire = 0)
        {
            $key = $key . '#' . $expire;
            $this->buffer[$key] = $value;
            return $this->commit();
        }

        public function get($key, $defaultValue = null)
        {
            $pattern = strstr($key, '#') ? $key : $key . '#';
            $rows = $this->search($pattern);
            if (count($rows)) {
                $key = Arrays::first($rows);
                return $this->buffer[$key] ? $this->buffer[$key] : $defaultValue;
            }
            return $defaultValue;
        }

        public function expire($key, $ttl = 3600)
        {
            $val = $this->get($key);
            return $this->set($key, $val, time() + $ttl);
        }

        public function incr($key, $by = 1)
        {
            $val = $this->get($key);
            if (null === $val) {
                $val = 1;
            } else {
                $val = (int) $val;
                $val += $by;
            }
            $this->set($key, $val);
            return $val;
        }

        public function decr($key, $by = 1)
        {
            $val = $this->get($key);
            if (null === $val) {
                $val = 0;
            } else {
                $val = (int) $val;
                $val -= $by;
                $val = 0 > $val ? 0 : $val;
            }
            $this->set($key, $val);
            return $val;
        }

        public function delete($key)
        {
            return $this->del($key);
        }

        public function del($key)
        {
            $rows = $this->search($key . '#');
            if (count($rows)) {
                $key = Arrays::first($rows);
                unset($this->buffer[$key]);
                return $this->commit();
            }
            return $this;
        }

        private function search($pattern)
        {
            $collection = array();
            if (count($this->buffer)) {
                foreach ($this->buffer as $key => $row) {
                    if (strstr($key, $pattern)) {
                        array_push($collection, $key);
                    }
                }
            }
            return $collection;
        }

        private function clean()
        {
            if (count($this->buffer)) {
                foreach ($this->buffer as $key => $row) {
                    if (strstr($key, '#')) {
                        list($keyName, $expire) = explode('#', $key, 2);
                        if ($expire < time()) {
                            $this->del($key);
                        }
                    }
                }
            }
        }

        public function backup()
        {
            $this->commit();
            $bucket = new Bucket(SITE_NAME);
            return $bucket->backup($this->db);
        }
    }
