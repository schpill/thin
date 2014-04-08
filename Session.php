<?php
    /**
     * Session class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Session
    {
        public $_sessionName;
        public $_isLocked = false;
        public $_duration = 3600;

        public static function instance($name, $duration = 3600)
        {
            if (null === Utils::get('__Thin__Session__' . $name)) {
                $instance = new self($name, $duration);
                Utils::set('__Thin__Session__' . $name, $instance);
                return $instance;
            }
            return Utils::get('__Thin__Session__' . $name);
        }

        public function __construct($name, $duration = 3600)
        {
            $this->_duration = $duration;
            if (!isset($_SESSION)) {
                session_start();
            }
            $this->_sessionName = $name;
            if (!Arrays::exists('__Thin__', $_SESSION)) {
                $_SESSION['__Thin__'] = array();
            }
            if (!Arrays::exists($this->_sessionName, $_SESSION['__Thin__'])) {
                $_SESSION['__Thin__'][$this->_sessionName] = array();
                $_SESSION['__Thin__'][$this->_sessionName]['__timeout__'] = time() + $duration;
                $_SESSION['__Thin__'][$this->_sessionName]['__start__'] = time();
            } else {
                $this->checkTimeout();
                $this->fill($_SESSION['__Thin__'][$this->_sessionName]);
            }
            return $this;
        }

        public function fill(array $datas)
        {
            foreach ($datas as $k => $v) {
                $this->$k = $v;
            }
            return $this->save();
        }

        public function save()
        {
            if (!Arrays::exists('__Thin__', $_SESSION)) {
                $_SESSION['__Thin__'] = array();
            }
            if (!Arrays::exists($this->_sessionName, $_SESSION['__Thin__'])) {
                $_SESSION['__Thin__'][$this->_sessionName] = array();
                $_SESSION['__Thin__'][$this->_sessionName]['__timeout__'] = time() + $this->_duration;
                $_SESSION['__Thin__'][$this->_sessionName]['__start__'] = time();
            }
            $tab = (array) $this;
            unset($tab['_sessionName']);
            unset($tab['_isLocked']);
            foreach ($tab as $key => $value) {
                $_SESSION['__Thin__'][$this->_sessionName][$key] = $value;
            }
            $_SESSION['__Thin__'][$this->_sessionName]['__timeout__'] = time() + $this->_duration;
            $_SESSION['__Thin__'][$this->_sessionName]['__start__'] = time();
            return $this;
        }

        public function lock()
        {
            $this->_isLocked = true;
            return $this;
        }

        public function unlock()
        {
            $this->_isLocked = false;
            return $this;
        }

        public function __call($func, $argv)
        {
            if (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (isset($this->$var)) {
                    $this->checkTimeout();
                    return $this->$var;
                } else {
                    return null;
                }
            } elseif (substr($func, 0, 3) == 'set') {
                $value = $argv[0];
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (false === $this->_isLocked) {
                    $this->$var = $value;
                }
                return $this->save();
            } elseif (substr($func, 0, 6) == 'forget') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (false === $this->_isLocked) {
                    $this->erase($var);
                    return $this;
                }
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
            if (false === $this->_isLocked) {
                $this->$var = $value;
            }
            return $this->save();
        }

        public function __get($var)
        {
            $this->checkTimeout();
            if (false === $this->_isLocked) {
                $this->$var;
            }
        }

        public function put($key, $value)
        {
            $this->$key = $value;
            return $this->save();
        }

        public function checkTimeout()
        {
            if (Arrays::exists('__Thin__', $_SESSION)) {
                if (Arrays::exists($this->_sessionName, $_SESSION['__Thin__'])) {
                    $timeout = $_SESSION['__Thin__'][$this->_sessionName]['__timeout__'];
                    $start = $_SESSION['__Thin__'][$this->_sessionName]['__start__'];
                    if ($timeout + $start < time()) {
                        $this->erase();
                    }
                }
            }
        }

        public function erase($key = null)
        {
            if (!Arrays::exists('__Thin__', $_SESSION)) {
                $_SESSION['__Thin__'] = array();
            }
            if (null === $key) {
                unset($_SESSION['__Thin__'][$this->_sessionName]);
                return $this;
            } else {
                if (Arrays::exists($key, $_SESSION['__Thin__'][$this->_sessionName])) {
                    $_SESSION['__Thin__'][$this->_sessionName][$key] = null;
                    unset($_SESSION['__Thin__'][$this->_sessionName][$key]);
                    return $this;
                } else {
                    $logger = Utils::get('ThinLog');
                    $logger->notice("The key $key does not exist in this session.");
                }
            }
        }

        public function event($id, \Closure $closure)
        {
            return $this->put(sha1($id), $closure);
        }
    }
