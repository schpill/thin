<?php
    namespace Thin;

    class Flood
    {
        public function check()
        {
            $localhost  = $_SERVER['REMOTE_ADDR'] == '127.0.0.1' || strstr($_SERVER['REMOTE_ADDR'], '192.168.')
            ? true
            : false;

            if (false === $localhost) {
                $ip         = $_SERVER['REMOTE_ADDR'];
                $key        = $ip . '::' . date('dmYHi') . '::flood';

                $val = redis()->incr($key);
                redis()->expire($key, 60);

                if ($val > Config::get('application.flood.max', 30)) {
                    die('Flood');
                }
            }
        }
    }
