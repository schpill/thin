<?php
    /**
     * Session "bis" class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Sessionbis extends Data
    {
        public $_sessionName;
        public $_session;
        public $_key;
        public $_data;
        public $_isLocked = false;
        public $_duration = 3600;

        public static function instance($name, $duration = 3600)
        {
            if (null === Utils::get('__Thin__Sessionbis__' . $name)) {
                $instance = new self($name, $duration);
                Utils::set('__Thin__Sessionbis__' . $name, $instance);
                return $instance;
            }
            return Utils::get('__Thin__Sessionbis__' . $name);
        }

        public function __construct($name, $duration = 3600)
        {
            $this->checkTimeout();
            $ip              = static::getIP();
            $this->_duration = $duration;
            $this->_key      = sha1($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . date('dmY'));

            $this->_sessionName = $name;
            $sessions = parent::query('thinsession', 'session_name = ' . $this->_sessionName . ' && session_key = ' . $this->_key . ' && expire >= ' . time());
            if (count($sessions)) {
                $this->_session = parent::getObject(current($sessions));
            } else {
                $create                     = array();
                $create['expire']           = time() + $duration;
                $create['session_name']     = $this->_sessionName;
                $create['session_key']      = $this->_key;
                $create['data']             = new \thinDataSession;
                $newId                      = parent::add('thinsession', $create);
                $this->_session             = parent::getById('thinsession', $newId);
            }
            $data = $this->_session->getData();
            return $data;
        }

        public function fill(array $datas)
        {
            $data = $this->_session->getData();
            foreach ($datas as $k => $v) {
                $data->$k = $v;
            }
            return $this->save();
        }

        public function save()
        {
            $edit                     = array();
            $edit['expire']           = time() + $this->_duration;
            $edit['session_name']     = $this->_sessionName;
            $edit['session_key']      = $this->_key;
            $edit['data']             = $this->_session->getData();
            $id                       = parent::edit('thinsession', $this->_session->getId(), $edit);
            $this->_session           = parent::getById('thinsession', $id);

            $data = $this->_session->getData();
            return $data;
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
                $data = $this->_session->getData();
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (isset($data->$var)) {
                    $this->checkTimeout();
                    return $data->$var;
                } else {
                    return null;
                }
            } elseif (substr($func, 0, 3) == 'set') {
                $data = $this->_session->getData();
                $value = $argv[0];
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (false === $this->_isLocked) {
                    $data->$var = $value;
                }
                return $this->save();
            } elseif (substr($func, 0, 6) == 'forget') {
                $data = $this->_session->getData();
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (false === $this->_isLocked) {
                    $this->erase($var);
                    return $data;
                }
                return $this->save();
            }
            if (!is_callable($func) || substr($func, 0, 3) !== 'set' || substr($func, 0, 3) !== 'get') {
                throw new \BadMethodCallException(__class__ . ' => ' . $func);
            }
        }

        public function __set($var, $value)
        {
            $data = $this->_session->getData();
            if (false === $this->_isLocked) {
                $data->$var = $value;
            }
            return $this->save();
        }

        public function __get($var)
        {
            $data = $this->_session->getData();
            $this->checkTimeout();
            if (false === $this->_isLocked) {
                $data->$var;
            }
        }

        public function checkTimeout()
        {
            $sessions = parent::query('thinsession', 'expire < ' . time());
            if (count($sessions)) {
                foreach ($sessions as $session) {
                    $session    = parent::getObject($session);
                    $delete     = parent::delete('thinsession', $session->getId());
                }
            }
        }

        public function erase($key = null)
        {
            if (null === $key) {
                $delete = parent::delete('thinsession', $this->_session->getId());
                return new self($this->_sessionName);
            } else {
                $data = $this->_session->getData();
                $data->$key = null;
                return $this->save();
            }
        }

        private static function getIP()
        {
            $ipAddress = '';
            if (getenv('HTTP_CLIENT_IP')) {
                $ipAddress = getenv('HTTP_CLIENT_IP');
            } else if(getenv('HTTP_X_FORWARDED_FOR')) {
                $ipAddress = getenv('HTTP_X_FORWARDED_FOR');
            } else if(getenv('HTTP_X_FORWARDED')) {
                $ipAddress = getenv('HTTP_X_FORWARDED');
            } else if(getenv('HTTP_FORWARDED_FOR')) {
                $ipAddress = getenv('HTTP_FORWARDED_FOR');
            } else if(getenv('HTTP_FORWARDED')) {
                $ipAddress = getenv('HTTP_FORWARDED');
            } else if(getenv('REMOTE_ADDR')) {
                $ipAddress = getenv('REMOTE_ADDR');
            } else {
                $ipAddress = 'UNKNOWN';
            }
             return $ipAddress;
        }
    }
