<?php
    namespace Thin;

    class Lang
    {
        public static function get($id, $default, $args = [])
        {
            defined ('REDIS_ACTIVE') || define('REDIS_ACTIVE', false);

            if (false === REDIS_ACTIVE) {
                return count($args) ? static::assign($default, $args) : $default;
            }

            $defaultLng = Config::get(
                'application.language',
                DEFAULT_LANGUAGE
            );

            $lng = lng();

            $cache      = redis()->get('lang.' . $lng . '.' . $id);
            $cacheEn    = redis()->get('lang.en.' . $id);

            if (strlen($cache) && strlen($cacheEn)) {
                return $cache;
            }

            if ($defaultLng == $lng) {
                $translation = count($args) ? static::assign($default, $args) : $default;
                $save = $default;
            } else {
                $row = self::getDb()->where(['key', '=', $id])->where(['language', '=', $lng])->first(true);

                if ($row) {
                    $translation = count($args) ? static::assign($row->translation, $args) : $row->translation;
                    $save = $row->translation;
                } else {
                    $translation = count($args) ? static::assign($default, $args) : $default;
                    $save = $default;

                    self::getDb()->create(['key' => $id, 'language' => $lng, 'translation' => $save])->save();
                }
            }

            redis()->set('lang.' . $lng . '.' . $id, $save);

            if (!strlen($cacheEn)) {
                redis()->set('lang.en.' . $id, $save);
                self::getDb()->create(['key' => $id, 'language' => 'en', 'translation' => $save])->save();
            }

            return $translation;
        }

        public static function purgeCache($lng = '*')
        {
            $keys = redis()->keys('lang.' . $lng . '.*');

            if (count($keys)) {
                foreach ($keys as $key) {
                    redis()->del($key);
                }
            }
        }

        public static function makeFrom($to, $from = null)
        {
            $defaultLng = Config::get(
                'application.language',
                DEFAULT_LANGUAGE
            );

            $from = is_null($from) ? $defaultLng : $from;

            $keys = redis()->keys('lang.' . $from . '.*');

            if (count($rows)) {
                foreach ($rows as $row) {
                    $save = redis()->get($row);
                    list($dummy, $from, $id) = explode('.', $row, 3);

                    self::getDb()->create(['key' => $id, 'language' => $to, 'translation' => $save])->save();
                }
            }
        }

        private static function assign($string, $args = [])
        {
            if (!count($args)) {
                return $string;
            }

            foreach ($args as $k => $v) {
                $string = str_replace("##$k##", $v, $string);
            }

            return $string;
        }

        public static function getDb()
        {
            return bigDb('translation', SITE_NAME);
        }
    }
