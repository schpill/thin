<?php
    namespace Thin;
    class Quickdb
    {
        private $db;
        private $ns;
        private static $instances = array();

        public function __construct($ns = 'core')
        {
            $this->db = container()->redis();
            $this->ns = $ns;
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

        public function keys($pattern)
        {
            $collection = array();
            $pattern    = repl('*', '', $pattern);
            $rows       = $this->search($pattern);
            if (count($rows)) {
                foreach ($rows as $row) {
                    array_push($collection, $row);
                }
            }
            return $collection;
        }

        public function get($key)
        {
            $pattern    = strstr($key, '#') ? $key : $key . '#';
            $rows       = $this->search($pattern);
            if (count($rows)) {
                $key    = Arrays::first($rows);
                list($dummy, $data) = explode('|', $key, 2);
                return $data;
            }
            return null;
        }

        private function check($pattern)
        {
            $rows = $this->search($pattern);
            if (count($rows)) {
                foreach ($rows as $key) {
                    $this->db->srem($this->ns, $key);
                }
            }
        }

        public function set($key, $value, $expire = 0)
        {
            $this->check($key);
            $key = $key . '#' . $expire . '|' . $value;
            $this->db->sadd($this->ns, $key);
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
            $pattern = strstr($key, '#') ? $key : $key . '#';
            $rows = $this->search($pattern);
            if (count($rows)) {
                $key = Arrays::first($rows);
                $this->db->srem($this->ns, $key);
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
            $collection = array();
            $rows = $this->db->smembers($this->ns);
            if (count($rows)) {
                foreach ($rows as $key) {
                    if (strstr($key, $pattern)) {
                        array_push($collection, $key);
                    }
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
            $key = sha1($this->ns);
            $this->db->set($key, 1);
            return $this;
        }

        public function unlock()
        {
            $key = sha1($this->ns);
            $this->db->del($key);
            return $this;
        }

        public function isLock($file)
        {
            $key = sha1($this->ns);
            $data = $this->db->get($key);
            return strlen($data) ? true : false;
        }
    }
