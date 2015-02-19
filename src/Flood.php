<?php
    namespace Thin;

    class Flood
    {
        public function check()
        {
            /* CLI case */
            defined('APPLICATION_ENV') || define('APPLICATION_ENV', 'production');

            if ('production' == APPLICATION_ENV) {
                $ip = $_SERVER['REMOTE_ADDR'];

                $this->isBanned($ip);

                $key = 'ip.' . str_replace('.', '', $ip) . '.' . date('dmYHi') . '.flood';

                $val = redis()->incr($key);
                redis()->expire($key, 60);

                if ($val > Config::get('application.flood.maxPageByMinute', 30)) {
                    $this->checkedBanned($ip);
                    Api::forbidden();
                }
            }
        }

        private function isBanned($ip)
        {
            $row = rdb('core', 'banned')
            ->inCache(false)
            ->where(['ip', '=', (int) str_replace('.', '', $ip)])
            ->first(true);

            if ($row) {
                Api::forbidden();
            }
        }

        private function checkedBanned($ip)
        {
            $row = rdb('core', 'flood')
            ->inCache(false)->where(['ip', '=', (int) str_replace('.', '', $ip)])->first(true);

            if (!$row) {
                rdb('core', 'flood')
                ->create(['ip' => (int) str_replace('.', '', $ip), 'num' => 1])
                ->save();
            } else {
                $num = (int) $row->num;
                $num++;

                $row->setNum($num)->save();

                if ($num >= Config::get('application.flood.max.time', 3)) {
                    rdb('core', 'banned')
                    ->create(['ip' => (int) str_replace('.', '', $ip)])
                    ->save();
                }
            }
        }
    }
