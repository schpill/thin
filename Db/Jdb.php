<?php
    namespace Thin\Db;

    use Dbjson\Dbjson as Driver;

    class Jdb
    {
        public static function instance($db, $table)
        {
            return Driver::instance($db, $table);
        }
    }
