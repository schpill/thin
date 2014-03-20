<?php
    namespace Thin;
    use SQLite3;

    class Keyvalue
    {
        private $db;
        private $type;
        private $results    = array();
        private $collection = array();

        public function __construct($type)
        {
            $dbFile = STORAGE_PATH . DS . 'db' . DS . 'kv.db';
            if (!File::exists($dbFile)) {
                if (!is_dir(STORAGE_PATH . DS . 'db')) {
                    mkdir(STORAGE_PATH . DS . 'db');
                }
                touch($dbFile);
            }
            $this->db = new SQLite3($dbFile);
            $q = "SELECT * FROM sqlite_master WHERE type = 'table' AND name = 'rows'";
            $res = $this->db->query($q);
            if(false === $res->fetchArray()) {
                $this->db->exec('CREATE TABLE rows (id INTEGER PRIMARY KEY AUTOINCREMENT, entity, entity_id)');
            }
            $q = "SELECT * FROM sqlite_master WHERE type = 'table' AND name = 'datas'";
            $res = $this->db->query($q);
            if(false === $res->fetchArray()) {
                $this->db->exec('CREATE TABLE datas (id INTEGER PRIMARY KEY AUTOINCREMENT, key, value)');
            }
            $this->type = $type;
        }

        public function all()
        {
            $q = "SELECT entity_id FROM rows WHERE entity = '$this->type'";
            $res = $this->db->query($q);
            while ($row = $res->fetchArray()) {
                array_push($this->results, $row['entity_id']);
            }
            return $this;
        }

        public function get()
        {
            if (count($this->results)) {
                foreach ($this->results as $id) {
                    array_push($this->collection, $this->getData($id));
                }
            }
            return $this;
        }

        public function save($data)
        {
            $fields = $this->getFields();
            $id = Arrays::exists('id', $data) ? $data['id'] : $this->makeKey();
            $new = !Arrays::exists('id', $data);

            if (true === $new) {
                $data['date_create'] = time();
                $this->addValue($id . '.date_create', time());
            }

            if (count($data) && Arrays::isAssoc($data)) {
                foreach ($fields as $field => $info) {
                    $ley = $id . '.' . Inflector::lower($field);
                    $val = Arrays::exists($field, $data) ? $data[$field] ? null;
                    if (empty($val)) {
                        if (!Arrays::exists('canBeNull', $info)) {
                            if (!Arrays::exists('default', $info)) {
                                throw new Exception('The field ' . $field . ' cannot be null.');
                            } else {
                                $val = $info['default'];
                            }
                        }
                    } else {
                        if (Arrays::exists('sha1', $info)) {
                            if (!preg_match('/^[0-9a-f]{40}$/i', $val) || strlen($val) != 40) {
                                $val = sha1($val);
                            }
                        } elseif (Arrays::exists('md5', $info)) {
                            if (!preg_match('/^[0-9a-f]{32}$/i', $val) || strlen($val) != 32) {
                                $val = md5($val);
                            }
                        }
                    }
                    $this->addValue($key, $val);
                }
            }
        }

        public function addValue($key, $value)
        {
            $exists = $this->getValue($key);
            if (false === $exists) {
                $q = "INSERT INTO datas (key, value) VALUES ('" . $key . "', '" . \SQLite3::escapeString($value) . "')";
            } else {
                $q = "UPDATE datas SET value = '" . \SQLite3::escapeString($value) . "' WHERE key = '" . \SQLite3::escapeString($key) . "";
            }
            $this->db->exec($q);
            return $this;
        }

        public function getValue($key)
        {
            $q = "SELECT value FROM datas WHERE key = '" . \SQLite3::escapeString($key) . "'";
            $res = $this->db->query($q);
            while ($row = $res->fetchArray()) {
                return $row['value'];
            }
            return false;
        }

        public function isTuple($key, $value)
        {
            $q = "SELECT key FROM datas WHERE value = '" . \SQLite3::escapeString($value) . "' AND key LIKE '%." . \SQLite3::escapeString($key) . "'";
            $res = $this->db->query($q);
            $collection = array();
            while ($row = $res->fetchArray()) {
                list($tmpKey, $tmpField) =  $row['key'];
                if ($this->type == $this->getEntity($tmpKey)) {
                    return true;
                }
            }
            return false;
        }

        public function getEntity($key)
        {
            $q = "SELECT entity FROM rows WHERE entity_id = '" . \SQLite3::escapeString($key) . "'";
            $res = $this->db->query($q);
            while ($row = $res->fetchArray()) {
                return $row['entity'];
            }
            return null;
        }

        public function getData($id, $array = false)
        {
            $fields = $this->getFields();
            $object = new Object;
            $oject->thin_kv = $this->type;
            $oject->id = $this->id;

            if (count($fields)) {
                foreach ($fields as $field => $info) {
                    $key = $id . '.' . Inflector::lower($field);
                    $q = "SELECT value FROM datas WHERE key = '" . \SQLite3::escapeString($key) . "'";
                    $res = $this->db->query($q);
                    while ($row = $res->fetchArray()) {
                        $object->$field = $row['value'];
                    }
                }
            }
            return $object;
        }

        public function getFields()
        {
            return Arrays::exists($this->type, Data::$_fields)
            ? Data::$_fields[$this->type]
            : Data::noConfigFields($this->type);
        }
        public function getSettings()
        {
            $return Arrays::exists($this->type, Data::$_settings)
            ? Data::$_settings[$this->type]
            : Data::defaultConfig($this->type);
        }

        public function exists($key)
        {
            $q = "SELECT * FROM rows WHERE entity = '$this->type' && entity_id = '" . \SQLite3::escapeString($key) . "'";
            $res = $this->db->query($q);
            return false === $res->fetchArray() ? true : false;
        }

        public function makeKey($keyLength = 9)
        {
            $key            = Inflector::quickRandom($keyLength);
            $check          = $this->exists($key);

            if (true === $check) {
                return $this->makeKey();
            }
            return $key;
        }
    }
