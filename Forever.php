<?php
    namespace Thin;

    class Forever
    {
        private static $instances = array();
        private $values = array();
        private $key;
        private $db;

        public function __construct($ns, $store = 'Thin\\Store')
        {
            $ns = 'thin_' . $ns;
            $c = cookies()->$ns;
            if (null === $c) {
                setcookie($ns, Utils::token(), strtotime('+1 year'));
            } else {
                setcookie($ns, $c, strtotime('+1 year'));
            }
            $this->key = cookies()->$ns;
            if (empty($this->key)) {
                throw new Exception("Cookies must be functional to execute this class.");
            }
            $this->model($ns);
            $this->db = new $store('thin_forever_' . $ns);
            $this->fetch();
        }

        public static function instance($ns)
        {
            $i = isAke(static::$instances, $ns, null);
            if (empty($i)) {
                $i = static::$instances[$ns] = new self($ns);
            }
            return $i;
        }

        public function fetch($name = null, $returnRow = false)
        {
            if (empty($name)) {
                return $this->populate($this->db->findByKey($this->key));
            } else {
                $row = $this->db->where('key = ' . $this->key)->where('name = ' . $name)->first();
                if (true === $returnRow) {
                    return $row;
                } else {
                    return empty($row) ? null : $row->getValue();
                }
            }
        }

        private function populate($tab)
        {
            if (!empty($tab)) {
                foreach ($tab as $row) {
                    $metas = $row->assoc();
                    $this->values[$metas['name']] = $metas['value'];
                }
            }
            return $this;
        }

        public function delete($name = null)
        {
            if (!empty($name)) {
                $row = $this->fetch($name);
                if (!empty($row)) {
                    $row->trash();
                }
                $value = isAke($this->values, $name, null);
                if (!empty($value)) {
                    unset($this->values[$name]);
                }
            } else {
                foreach ($this->values as $k => $v) {
                    $this->delete($k);
                }
            }
            return $this;
        }

        public function store($name = null)
        {
            if (!empty($name)) {
                $row = $this->fetch($name, true);
                $exists = !empty($row);
                if (true === $exists) {
                    $this->update($row, $name);
                } else {
                    $this->insert($name);
                }
            } else {
                foreach ($this->values as $k => $v) {
                    $this->store($k);
                }
            }
            return $this;
        }

        private function update($row, $name)
        {
            $value = isAke($this->values, $name, null);
            $row->$name = $value;
            $row->store();
            return $this;
        }

        private function insert($name)
        {
            $value = isAke($this->values, $name, null);
            $this->db->make()->setName($name)->setValue($value)->store();
            return $this;
        }

        private function model($ns)
        {
            $fields = array(
                'name'          => array(),
                'value'         => array('canBeNull' => true),
                'key'           => array('default'   => $this->key)
            );
            $conf = array(
                'checkTuple'    => array('name', 'key'),
                'functions'     => array()
            );
            data('thin_forever_' . $ns, $fields, $conf);
        }

        public function __call($f, $a)
        {
            if (substr($f, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($f, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                return isset($this->$var) ? $this->$var : null;
            } elseif (substr($f, 0, 3) == 'has') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($f, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                return isset($this->$var);
            } elseif (substr($f, 0, 3) == 'set') {
                $value = Arrays::first($a);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($f, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                $this->$var = $value;
                return $this;
            }
        }

        public function __set($k, $v)
        {
            $this->values[$k] = $v;
            $this->store($k);
            return $this;
        }

        public function __isset($k)
        {
            return Arrays::exists($k, $this->values);
        }

        public function __get($k)
        {
            return isset($this->$k) ? $this->values[$k] : null;
        }
    }
