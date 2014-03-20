<?php
    namespace Thin;
    use PDO;
    use SqliteRow;
    class Sqlite
    {
        private $db;
        public $table;

        public function __construct($database, $table = nill)
        {
            $this->db = $this->connect($database);
            $this->table = $table;
        }

        public function connect($db)
        {
            $dbFile = STORAGE_PATH . DS . 'db' . DS . $db . '.db';
            return new PDO('sqlite:' . $dbFile);
        }

        public function getPrimaryKey($table = null)
        {
            $this->table = !empty($table) ? $table : $this->table;
            if(!empty($this->table)) {
                $stmt = $db->prepare("SELECT * FROM sqlite_master WHERE type='table' AND name=:name");
                $stmt->execute(array(':name' => $this->table));
                $result = $stmt->fetch();
                $sql = $result['sql'];

                $matches = array();
                preg_match('/(\w+?)\s+\w+?\s+PRIMARY KEY/', $sql, $matches);

                if(isset($matches[1])) {
                    return $matches[1];
                }
            }
            return null;
        }

        public function getColumns($table = null)
        {
            $this->table = !empty($table) ? $table : $this->table;
            if(!empty($this->table)) {
                $stmt = $this->db->query("PRAGMA table_info($this->table)");
                $columns = array();
                while($row = $stmt->fetch()) {
                    array_push($columns, $row['name']);
                }
                return $columns;
            }
            return null;
        }

        public static function select($sql)
        {
            $res = $db->query($sql);
            $collection = array();
            if (!empty($res)) {
                foreach ($res as $row) {
                    $obj = new SqliteRow;
                    foreach ($row as $k => $v) {
                        if (!is_numeric($k)) {
                            $obj->$k = $v;
                        }
                    }
                    array_push($collection, $obj);
                }
            }
            return $collection;
        }
    }
