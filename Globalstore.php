<?php
    namespace Thin;

    /**
     * A storage using GLOBALS.
     */
    class Globalstore
    {
        public $key, $keyTimeOut;

        public function __construct($db = null, $table = null)
        {
            $db     = is_null($db) ? 'global' : $db;
            $table  = is_null($table) ? 'store' : $table;

            $this->key          = "$db::$table";
            $this->keyTimeOut   = "$db::$table::ttl";

            if (!isset($GLOBALS[$this->key])) {
                $GLOBALS[$this->key] = [];
            }

            if (!isset($GLOBALS[$this->keyTimeOut])) {
                $GLOBALS[$this->keyTimeOut] = [];
            }
        }

        public static function instance($db, $table)
        {
            $db     = is_null($db) ? 'global' : $db;
            $table  = is_null($table) ? 'store' : $table;

            $key    = sha1($db . $table);
            $has    = Instance::has('GlobalStore', $key);

            if (true === $has) {
                return Instance::get('GlobalStore', $key);
            } else {
                return Instance::make('GlobalStore', $key, new self($db, $table));
            }
        }

        public function set($key, $data, $ttl = 0)
        {
            $GLOBALS[$this->key][$key] = $data;

            $ttl = 0 < $ttl ? time() + $ttl : $ttl;

            $GLOBALS[$this->keyTimeOut][$key] = $ttl;

            return $this;
        }

        public function setex($key, $data, $ttlMinute = 0)
        {
            return $this->set($key, $data, $ttlMinute * 60);
        }

        public function expire($key, $ttl = 0)
        {
            if ($this->has($key)) {
                $data = $this->get($key);

                return $this->set($key, $data, $ttl * 60);
            }

            return $this;
        }

        public function incr($key, $by = 1)
        {
            $val = (int) $this->get($key, 0);

            return $this->set($key, $val + $by);
        }

        public function decr($key, $by = 1)
        {
            $val = (int) $this->get($key, 0);

            return $this->set($key, $val - $by);
        }

        public function incrBy($key, $by = 1)
        {
            return $this->incr($key, $by);
        }

        public function decrBy($key, $by = 1)
        {
            return $this->decr($key, $by);
        }

        public function get($key, $default = null)
        {
            $this->clean();

            $tab = isAke($GLOBALS, $this->key, []);

            return isAke($tab, $key, $default);
        }

        public function delete($key)
        {
            return $this->del($key);
        }

        public function forget($key)
        {
            return $this->del($key);
        }

        public function del($key)
        {
            if ($this->has($key)) {
                unset($GLOBALS[$this->key][$key]);
                unset($GLOBALS[$this->keyTimeOut][$key]);
            }

            return $this;
        }

        public function keys($pattern = '*')
        {
            $collection = [];

            $this->clean();

            $tab = isAke($GLOBALS, $this->key, []);

            if (count($tab)) {
                foreach ($tab as $key => $value) {
                    if (fnmatch($pattern, $key)) {
                        array_push($collection, $key);
                    }
                }
            }

            return $collection;
        }

        public function has($key)
        {
            $check = Utils::token();

            return $check != $this->get($key, $check);
        }

        public function flush()
        {
            unset($GLOBALS[$this->key]);
            unset($GLOBALS[$this->keyTimeOut]);

            return $this;
        }

        public function duplicate(Globalstore $to)
        {
            $GLOBALS[$to->key]         = $GLOBALS[$this->key];
            $GLOBALS[$to->keyTimeOut]  = $GLOBALS[$this->keyTimeOut];

            unset($GLOBALS[$this->key]);
            unset($GLOBALS[$this->keyTimeOut]);

            return $this;
        }

        private function clean()
        {
            $tab    = isAke($GLOBALS, $this->key, []);
            $ttlTab = isAke($GLOBALS, $this->keyTimeOut, []);

            if (count($tab)) {
                foreach ($tab as $key => $value) {
                    $ttl = isAke($ttlTab, $key, 0);

                    if ($ttl > 0) {
                        if (time() > $ttl) {
                            unset($GLOBALS[$this->key][$key]);
                            unset($GLOBALS[$this->keyTimeOut][$key]);
                        }
                    }
                }
            }

            return $this;
        }

        public function __call($method, $args)
        {
            if (substr($method, 0, 3) == 'get' && strlen($method) > 3) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 3)));
                $var = Inflector::lower($uncamelizeMethod);

                if (count($args) == 1) {
                    $default = Arrays::first($args);
                } else {
                    $default = null;
                }

                return $this->get($var, $default);
            } elseif (substr($method, 0, 3) == 'set' && strlen($method) > 3) {
                if (count($args) == 2) {
                    $ttl = Arrays::last($args);
                } else {
                    $ttl = 0;
                }

                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 3)));
                $var = Inflector::lower($uncamelizeMethod);

                return $this->set($var, Arrays::first($args), $ttl);
            } else {
                throw new \BadMethodCallException(__class__ . ' => ' . $func);
            }
        }

        public static function __callStatic($method, $args)
        {
            $db = new self;

            if (substr($method, 0, 3) == 'get' && strlen($method) > 3) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 3)));
                $var = Inflector::lower($uncamelizeMethod);

                if (count($args) == 1) {
                    $default = Arrays::first($args);
                } else {
                    $default = null;
                }

                return $db->get($var, $default);
            } elseif (substr($method, 0, 3) == 'set' && strlen($method) > 3) {
                if (count($args) == 2) {
                    $ttl = Arrays::last($args);
                } else {
                    $ttl = 0;
                }

                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 3)));
                $var = Inflector::lower($uncamelizeMethod);

                return $db->set($var, Arrays::first($args), $ttl);
            } else {
                throw new \BadMethodCallException(__class__ . ' => ' . $func);
            }
        }
    }
