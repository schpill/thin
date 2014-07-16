<?php
    namespace Thin;
    use PDO;

    class Kvdb
    {
        private $db;
        private $result;
        private static $instance;

        public function __construct($config = null)
        {
            if (is_null($config)) {
                $configs        = container()->getConfig()->getDb();
                $config         = isAke($configs, 'db');
                if (empty($config)) {
                    throw new Exception("The database configuration is empty.");
                }
            }
            $this->config = $config;
            $this->connect();
            $this->check();
        }

        public static function instance($config = null)
        {
            if (is_null(static::$instance)) {
                static::$instance = new self($config);
            }
            return static::$instance;
        }

        public function db()
        {
            return $this->db;
        }

        public function keys($pattern)
        {
            $pattern = repl('*', '%', $pattern);
            $q = "SELECT kvs_db_id FROM kvs_db WHERE kvs_db_id LIKE '$pattern'";
            $res = $this->execute($q);
            if (Arrays::is($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return array();
            }
            $collection = array();
            foreach ($res as $row) {
                array_push($collection, $row['kvs_db_id']);
            }
            return $collection;
        }

        public function get($key, $default = null)
        {
            $q = "SELECT UNCOMPRESS(value) AS value FROM kvs_db WHERE kvs_db_id = " . $this->quote($key);
            $res = $this->execute($q);
            if (Arrays::is($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return $default;
            }
            foreach ($res as $row) {
                return $row['value'];
            }
        }

        public function set($key, $value, $expire = 0)
        {
            $q = "INSERT INTO kvs_db (kvs_db_id, value, expire)
            VALUES (
                " . $this->quote($key) . ",
                COMPRESS(" . $this->quote($value) . "),
                " . $this->quote($expire) . "
            )
            ON DUPLICATE KEY
            UPDATE value = COMPRESS(" . $this->quote($value) . "), expire = " . $this->quote($expire) . ";";
            $res = $this->execute($q);
            return $this;
        }

        public function expire($key, $ttl = 3600)
        {
            $val = $this->get($key);
            if (!empty($val)) {
                return $this->set($key, $val, time() + $ttl);
            }
            return false;
        }

        public function delete($key)
        {
            return $this->del($key);
        }

        public function del($key)
        {
            $q = "DELETE FROM kvs_db WHERE kvs_db_id = " . $this->quote($key);
            $res = $this->execute($q);
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

        private function quote($value, $parameterType = PDO::PARAM_STR)
        {
            if(null === $value) {
                return "NULL";
            }
            if (is_string($value)) {
                return $this->db->quote($value, $parameterType);
            }
            return $value;
        }

        private function execute($query)
        {
            $res = $this->db->prepare($query);
            $res->execute();
            return $res;
        }

        private function connect()
        {
            $dsn = $this->config->getDsn();
            if (empty($dsn)) {
                $dsn = $this->config->getAdapter()
                . ":dbname="
                . $this->config->getDatabase()
                . ";host="
                . $this->config->getHost();
            }
            $this->db = new PDO(
                $dsn,
                $this->config->getUsername(),
                $this->config->getPassword()
            );
        }

        private function check()
        {   $q = "CREATE TABLE IF NOT EXISTS `kvs_db` (
            `kvs_db_id` VARCHAR(255) NOT NULL,
            `value` LONGBLOB default NULL,
            `expire` BIGINT(20) UNSIGNED default 0,
            PRIMARY KEY (`kvs_db_id`)
            );";
            $this->execute($q);
            $q = "DELETE FROM kvs_db WHERE expire > 0 AND expire < " . time();
            $this->execute($q);
        }

        private function checkTable($table)
        {
            $res = $this->execute("SHOW TABLES");
            if (Arrays::is($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return false;
            }
            foreach ($res as $row) {
                $tabletmp = Arrays::first($row);
                if ($table == $tabletmp) {
                    return true;
                }
            }
            return false;
        }
    }
