<?php
    namespace Thin;

    class Lang
    {
        public static function get($id, $default, $args = [])
        {
            $defaultLng = Config::get(
                'application.language',
                DEFAULT_LANGUAGE
            );

            if ($defaultLng == lng()) {
                /* on crée automatiquement les lignes en anglais */
                if (!static::has($id, 'en')) {
                    static::set($id, 'en', $default);
                }

                return static::assign($default, $args);
            }

            $row = jmodel('translation')->where("key = $id")->where('language = ' . lng())->first(true);

            if ($row) {
                return static::assign($row->translation, $args);
            }

            return static::assign($default, $args);
        }

        public static function has($id, $lng)
        {
            $row = jmodel('translation')->where("key = $id")->where('language = ' . $lng)->first(true);

            return $row ? true : false;
        }

        public static function set($id, $lng, $translation)
        {
            return jmodel('translation')->create()->setKey($id)->setLanguage($lng)->setTranslation($translation)->save();
        }

        public static function forget($id, $lng)
        {
            return static::remove($id, $lng);
        }

        public static function remove($id, $lng)
        {
            $row = jmodel('translation')->where("key = $id")->where('language = ' . $lng)->first(true);

            if ($row) {
                return $row->delete();
            }

            return false;
        }

        private static function assign($string, $params = [])
        {
            if (!count($params)) {
                return $string;
            }

            foreach ($params as $k => $v) {
                $string = str_replace("##$k##", $v, $string);
            }

            return $string;
        }
    }
