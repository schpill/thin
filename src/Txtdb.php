<?php
    namespace Thin;

    class Txtdb
    {
        private $dir;
        private $result;
        private $data = array();

        public function __construct($ns = 'core')
        {
            $this->dir = STORAGE_PATH . DS . $ns;
            if (!is_dir($this->dir)) {
                mkdir($this->dir);
            }
            $this->clean();
        }

        private function data($pattern)
        {
            $hash = sha1($pattern);
            return isAke($this->data, $hash, null);
        }

        private function setData($pattern, $value)
        {
            $hash = sha1($pattern);
            $this->data[$hash] = $value;
        }

        private function load($file)
        {
            return fgc($file);
        }

        public function keys($pattern)
        {
            $collection = $this->data($pattern);
            if (!Arrays::is($collection)) {
                $oldPattern = $pattern;
                $pattern    .= '*#*';
                $pattern    = repl('**', '*', $pattern);
                $files      = $this->glob($this->dir . DS . $pattern);
                $collection = array();
                if (count($files)) {
                    foreach ($files as $row) {
                        array_push($collection, $row);
                    }
                }
                $this->setData($oldPattern, $collection);
            }
            return $collection;
        }

        public function get($key, $default = null)
        {
            if (!strstr($key, $this->dir)) {
                $files = $this->glob($this->dir . DS . $key . '#*');
                if (count($files)) {
                    $key = Arrays::first($files);
                }
            }
            if (File::exists($key)) {
                return $this->load($key);
            }
            return $default;
        }

        public function set($key, $value, $expire = 0)
        {
            $files = $this->glob($this->dir . DS . $key . '#*');
            if (count($files)) {
                $nkey = Arrays::first($files);
                if (File::exists($nkey)) {
                    File::delete($nkey);
                }
            }
            $key = $this->dir . DS . $key . '#' . $expire;
            File::put($key, $value);
            return $this;
        }

        public function expire($key, $value = null, $ttl = 3600)
        {
            $val = is_null($value) ? $this->get($key) : $value;
            return $this->set($key, $val, time() + $ttl);
        }

        public function delete($key)
        {
            return $this->del($key);
        }

        public function del($key)
        {
            if (!strstr($key, $this->dir)) {
                $key = $this->dir . DS . $key;
            }
            $files = $this->glob($key . '#*');
            if (count($files)) {
                $key = Arrays::first($files);
                if (File::exists($key)) {
                    File::delete($key);
                    $this->cleanGlob($key . '#*');
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

        private function cleanGlob($pattern)
        {
            $key = sha1($pattern . 'list');
            $file = $this->dir . DS . $key;
            File::delete($file);
        }

        private function glob($pattern)
        {
            $key = sha1($pattern . 'list');
            $file = $this->dir . DS . $key;
            if (File::exists($file)) {
                File::delete($file);
            }
            $data = glob($pattern, GLOB_NOSORT);
            File::put($file, json_encode($data));

            return json_decode($this->load($file), true);
        }

        private function clean()
        {
            $files = $this->glob($this->dir . DS . '*#*');
            if (count($files)) {
                $del = false;
                foreach ($files as $row) {
                    list($dummy, $expire) = explode('#', $row, 2);
                    if ($expire > 0 && $expire < time()) {
                        $del = true;
                        File::delete($row);
                    }
                }
                if (true === $del) {
                    $this->cleanGlob($this->dir . DS . '*#*');
                }
            }
        }
    }
