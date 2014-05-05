<?php
    namespace Thin;
    class Mysql
    {
        private $_db;
        private $_result;
        private $_config;

        public function __construct()
        {
            $this->_config = include(CONFIG_PATH . DS . 'mysql.php');
            $this->_connect();
        }

        public function db()
        {
            return $this->_db;
        }

        public function get($query)
        {
            $collection = array();
            $results = mysql_query($query);
            while ($row = mysql_fetch_array($results, MYSQL_ASSOC)) {
                $obj = newObj('Row');
                $obj->populate($row);
                $collection[] = $obj;
            }
            return $collection;
        }

        public function __destruct()
        {
            if ($this->_result) {
                $this->free();
            }
            $this->_disconnect();
        }

        public function execute($query)
        {
            $this->_result = mysql_query($query);
            return $this->_result;
        }

        public function next()
        {
            return mysql_fetch_array($this->_result);
        }

        public function count() {
            return mysql_num_rows($this->_result);
        }

        public function free()
        {
            if ($this->_result) {
                @mysql_free_result($this->_result);
            }
        }

        public function escape($string)
        {
            return mysql_escape_string($string);
        }

        private function _connect()
        {
            $this->_db = mysql_connect($this->_config['host'], $this->_config['user'], $this->_config['password']);
            mysql_select_db($this->_config['database']);
            unset($this->_config);
        }

        private function _disconnect()
        {
            mysql_close($this->_db);
        }

        public function beginTransaction()
        {
            return mysql_query('BEGIN', $this->_db);
        }

        public function commit()
        {
            return mysql_query('COMMIT', $this->_db);
        }

        public function rollback()
        {
            return mysql_query('ROLLBACK', $this->_db);
        }
    }
