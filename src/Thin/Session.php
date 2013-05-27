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
            if (null === \u::get('__Thin__Session__' . $name)) {
                $instance = new self($name, $duration);
                \u::set('__Thin__Session__' . $name, $instance);
                return $instance;
            }
            return \u::get('__Thin__Session__' . $name);
        }

        public function __construct($name, $duration = 3600)
        {
            $this->_duration = $duration;
            if (!isset($_SESSION)) {
                session_start();
            }
            $this->_sessionName = $name;
            if (!ake('__Thin__', $_SESSION)) {
                $_SESSION['__Thin__'] = array();
            }
            if (!ake($this->_sessionName, $_SESSION['__Thin__'])) {
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
            if (!ake('__Thin__', $_SESSION)) {
                $_SESSION['__Thin__'] = array();
            }
            if (!ake($this->_sessionName, $_SESSION['__Thin__'])) {
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
                $uncamelizeMethod = \i::uncamelize(lcfirst(substr($func, 3)));
                $var = \i::lower($uncamelizeMethod);
                if (isset($this->$var)) {
                    $this->checkTimeout();
                    return $this->$var;
                } else {
                    return null;
                }
            } elseif (substr($func, 0, 3) == 'set') {
                $value = $argv[0];
                $uncamelizeMethod = \i::uncamelize(lcfirst(substr($func, 3)));
                $var = \i::lower($uncamelizeMethod);
                if (false === $this->_isLocked) {
                    $this->$var = $value;
                }
                return $this->save();
            } elseif (substr($func, 0, 6) == 'forget') {
                $uncamelizeMethod = \i::uncamelize(lcfirst(substr($func, 3)));
                $var = \i::lower($uncamelizeMethod);
                if (false === $this->_isLocked) {
                    $this->erase($var);
                    return $this;
                }
                return $this->save();
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

        public function checkTimeout()
        {
            $timeout = $_SESSION['__Thin__'][$this->_sessionName]['__timeout__'];
            $start = $_SESSION['__Thin__'][$this->_sessionName]['__start__'];
            if ($timeout + $start < time()) {
                $this->erase();
            }
        }

        public function erase($key = null)
        {
            if (null === $key) {
                $params = $_SESSION['__Thin__'][$this->_sessionName];
                foreach ($params as $key => $value) {
                    $this->$key = null;
                }
                $_SESSION['__Thin__'][$this->_sessionName] = array();
            } else {
                if (ake($key, $_SESSION['__Thin__'][$this->_sessionName])) {
                    $_SESSION['__Thin__'][$this->_sessionName][$key] = null;
                } else {
                    $logger = \u::get('ThinLog');
                    $logger->notice("The key $key does not exist in this session.");
                }
            }
        }
    }
