<?php
    namespace Thin;
    class Sqlite
    {
        public static $error;

        public static function connect($db)
        {
            $dbFile = STORAGE_PATH . DS . 'db' . DS . $db . '.db';
            return new \PDO('sqlite:' . $dbFile);
        }

        public static function getPrimaryKey($db, $table)
        {
            $stmt = $db->prepare("SELECT * FROM sqlite_master WHERE type='table' AND name=:name");
            $stmt->execute(array(':name' => $table));
            $result = $stmt->fetch();
            $sql = $result['sql'];

            $matches = array();
            preg_match('/(\w+?)\s+\w+?\s+PRIMARY KEY/', $sql, $matches);

            if(!isset($matches[1])) {
                return null;
            }
            return $matches[1];
        }

        public static function getColumns($db, $table)
        {
            $stmt = $db->query("PRAGMA table_info($table)");
            $columns = array();
            while($row = $stmt->fetch()) {
                array_push($columns, $row['name']);
            }
            return $columns;
        }

        public static function select($db, $sql)
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
