<?php
    namespace Thin;

    class Textdb
    {
        private $db;
        private $result;

        public function __construct()
        {
            $this->db = new Jsondb('kvs_db');
            $this->check();
        }

        public function keys($pattern)
        {
            $pattern = repl('*', '%', $pattern);
            $res = $this->db->where("key LIKE $pattern")->exec();
            $collection = array();
            if (count($res)) {
                foreach ($res as $row) {
                    array_push($collection, $row['key']);
                }
            }
            return $collection;
        }

        public function get($key)
        {
            $res = $this->db->where("key = $key")->exec();
            if (count($res)) {
                foreach ($res as $row) {
                    return $row['value'];
                }
            }
            return null;
        }

        public function set($key, $value, $expire = 0)
        {
            $res = $this->db->where("key = $key")->exec();
            if (count($res)) {
                foreach ($res as $row) {
                    $row->setValue($value)->setExpire($expire)->save();
                }
            } else {
                $this->db->create()->setKey($key)->setValue($value)->setExpire($expire)->save();
            }
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
            $res = $this->db->where("key = $key")->exec(true);
            if (count($res)) {
                foreach ($res as $row) {
                    $row->delete();
                }
            }
            return $this;
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

        private function check()
        {
            $res = $this->db->where("expire > 0")->where("expire < " . time())->exec(true);
            if (count($res)) {
                foreach ($res as $row) {
                    $row->delete();
                }
            }
        }
    }
