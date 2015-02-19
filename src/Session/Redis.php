<?php
    namespace Thin\Session;
    use Thin\Instance;
    use Thin\Inflector;
    use Thin\Utils;
    use Thin\Redistorage as redisDB;

    class Redis
    {
        public $__db, $__ttl, $__namespace;

        public function __construct($namespace, $ttl = 3600)
        {
            $ns = 'thin_' . $namespace;
            $c = cookies()->$ns;
            if (null === $c) {
                setcookie($ns, Utils::token(), strtotime('+1 year'));
            } else {
                setcookie($ns, $c, strtotime('+1 year'));
            }
            $key                  = cookies()->$ns;
            $this->__namespace    = $namespace . '::' . $key;
            $this->__ttl          = $ttl;
            $this->__db           = redisDB::instance('core', 'session');
            $this->clean();
            $this->populate();
        }

        public static function instance($namespace, $ttl = 3600)
        {
            $key = sha1($namespace);
            $has = Instance::has('RedisSession', $key);
            if (true === $has) {
                return Instance::get('RedisSession', $key);
            } else {
                return Instance::make('RedisSession', $key, with(new self($namespace, $ttl)));
            }
        }

        public function __call($func, $argv)
        {
            if (substr($func, 0, 3) == 'get' && strlen($func) > 3) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (isset($this->$var)) {
                    $this->clean();
                    return $this->$var;
                } else {
                    return null;
                }
            } elseif (substr($func, 0, 3) == 'set' && strlen($func) > 3) {
                $value = $argv[0];
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                $this->$var = $value;
                return $this->save();
            } elseif (substr($func, 0, 6) == 'forget' && strlen($func) > 6) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 6)));
                $var = Inflector::lower($uncamelizeMethod);
                $this->erase($var);
                return $this->save();
            }
            $id = sha1($func);
            if (isset($this->$id)) {
                if ($this->$id instanceof \Closure) {
                    return call_user_func_array($this->$id , $argv);
                }
            }
            if (!is_callable($func) || substr($func, 0, 3) !== 'set' || substr($func, 0, 3) !== 'get') {
                throw new \BadMethodCallException(__class__ . ' => ' . $func);
            }
        }
        public function __set($var, $value)
        {
            $var = trim($var);
            $this->$var = $value;
            return $this->save();
        }

        public function __get($var)
        {
            $this->clean($this->$var);
            if (isset($this->$var)) {
                return $this->$var;
            }
            return null;
        }

        public function put($key, $value)
        {
            $this->$key = $value;
            return $this->save();
        }

        public function event($id, \Closure $closure)
        {
            return $this->put(sha1($id), $closure);
        }

        public function clean()
        {
            $res = $this->__db->where('expire < ' . time())->exec(true);
            if ($res->count() > 0) {
                $res->delete();
            }
            return $this;
        }

        public function populate()
        {
            $res = $this->__db->where('namespace = ' . $this->__namespace)->exec(true);
            if ($res->count() > 0) {
                foreach ($res->rows() as $row) {
                    $tab = $row->assoc();
                    $key = isAke($tab, 'key', null);
                    $value = isAke($tab, 'value', null);
                    $this->$key = $value;
                }
            }
            return $this;
        }

        public function save()
        {
            $tab = (array) $this;
            unset($tab['__db']);
            unset($tab['__ttl']);
            unset($tab['__namespace']);
            $expire = time() + $this->__ttl;
            foreach ($tab as $key => $value) {
                $this->erase($key);
                $row = $this->__db
                ->create()
                ->setNamespace($this->__namespace)
                ->setKey($key)
                ->setValue($value)
                ->setExpire($expire)
                ->save();
            }
            return $this;
        }

        function erase($key = null)
        {
            if (is_null($key)) {
                $res = $this->__db->where('namespace = ' . $this->__namespace)->exec(true);
            } else {
                $res = $this->__db->where('key = ' . $key)->where('namespace = ' . $this->__namespace)->exec(true);
            }
            if ($res->count() > 0) {
                $res->delete();
            }
            return $this;
        }
    }
