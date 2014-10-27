<?php
    namespace Thin;

    class Flood
    {
        public function check()
        {
            /* CLI case */
            if (!defined('APPLICATION_ENV')) {
                define('APPLICATION_ENV', 'production');
            }

            if ('production' == APPLICATION_ENV) {
                $ip = $_SERVER['REMOTE_ADDR'];

                $this->isBanned($ip);

                $key = $ip . '::' . date('dmYHi') . '::flood';

                $val = redis()->incr($key);
                redis()->expire($key, 60);

                if ($val > Config::get('application.flood.max.page.by.minute', 30)) {
                    $this->checkedBanned($ip);
                    die('Flood');
                }
            }
        }

        private function isBanned($ip)
        {
            $row = jdb('system', 'banned')->where('ip = ' . str_replace('.', '_', $ip))->first(true);

            if ($row) {
                die('Banned');
            }
        }

        private function checkedBanned($ip)
        {
            $row = jdb('system', 'flood')->where('ip = ' . str_replace('.', '_', $ip))->first(true);

            if (!$row) {
                jdb('system', 'flood')->create(['ip' => str_replace('.', '_', $ip), 'num' => 1])->save();
            } else {
                $num = (int) $row->num;
                $num++;

                $row->setNum($num)->save();

                if ($num >= Config::get('application.flood.max.time', 3)) {
                    jdb('system', 'banned')->create(['ip' => str_replace('.', '_', $ip)])->save();
                }
            }
        }
    }
