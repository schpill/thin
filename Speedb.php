<?php
    namespace Thin;

    class Speedb
    {
        private $db;
        private $buffer;
        private $commit = false;
        private static $instances = array();

        public function __construct($ns = 'core')
        {
            ini_set('memory', '1GB');
            $this->db = STORAGE_PATH . DS . $ns . '.speed';
            if (!File::exists($this->db)) {
                File::put($this->db, '');
            }
            $this->buffer = json_decode($this->load($this->db), true);
            $this->buffer = is_null($this->buffer) ? array() : $this->buffer;
            $this->clean();
        }

        public static function instance($ns = 'core')
        {
            $i = isAke(static::$instances, $ns, null);
            if (is_null($i)) {
                $i = new self($ns);
                static::$instances[$ns] = $i;
            }
            return $i;
        }

        public function __destruct()
        {
            if (true === $this->commit) {
                File::delete($this->db);
                File::put($this->db, json_encode($this->buffer));
            }
        }

        private function load($file)
        {
            return File::get($file);
        }

        public function keys($pattern)
        {
            $collection = array();
            $pattern    = repl('*', '', $pattern);
            $files      = $this->search($pattern);
            if (count($files)) {
                foreach ($files as $row) {
                    array_push($collection, $row);
                }
            }
            return $collection;
        }

        public function get($key)
        {
            $pattern = strstr($key, '#') ? $key : $key . '#';
            $files   = $this->search($pattern);
            if (count($files)) {
                $key    = Arrays::first($files);
                $data   = isAke($this->buffer, $key, null);
                return $data;
            }
            return null;
        }

        public function set($key, $value, $expire = 0)
        {
            $key = $key . '#' . $expire;
            $this->buffer[$key] = $value;
            $this->commit();
            return $this;
        }

        public function expire($key, $ttl = 3600)
        {
            $val = $this->get($key);
            return $this->set($key, $val, time() + $ttl);
        }

        public function delete($key)
        {
            return $this->del($key);
        }

        public function del($key)
        {
            $files = $this->search($key . '#');
            if (count($files)) {
                $key = Arrays::first($files);
                unset($this->buffer[$key]);
                $this->commit();
            }
            return $this;
        }

        private function commit()
        {
            $this->commit = true;
        }

        public function incr($key, $by = 1)
        {
            $val = $this->get($key);
            if (!strlen($val)) {
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
            if (!strlen($val)) {
                $val = 0;
            } else {
                $val = (int) $val;
                $val -= $by;
                $val = 0 > $val ? 0 : $val;
            }
            $this->set($key, $val);
            return $val;
        }

        private function search($pattern)
        {
            $keys = array_keys($this->buffer);
            $collection = array();
            foreach ($keys as $key) {
                if (strstr($key, $pattern)) {
                    array_push($collection, $key);
                }
            }
            return $collection;
        }

        private function clean()
        {
            $files = $this->search('#');
            if (count($files)) {
                foreach ($files as $key) {
                    list($dummy, $expire) = explode('#', $key, 2);
                    if ($expire > 0 && $expire < time()) {
                        $this->del($key);
                    }
                }
            }
        }

        public function lock($lock)
        {
            $file = repl('.speed', '.speedLock', $this->db);
            File::put($file, time());
            return $this;
        }

        public function unlock()
        {
            $file = repl('.speed', '.speedLock', $this->db);
            File::delete($file);
            return $this;
        }

        public function isLock($file)
        {
            $file = repl('.speed', '.speedLock', $this->db);
            return File::exists($file) ? 1 : '';
        }
    }
