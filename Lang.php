<?php
    namespace Thin;

    class Lang
    {
        public static function get($id, $default, $args = [])
        {
            $mvc = container()->getMvc();
            $mvc = empty($mvc) ? 'www::static::index' : $mvc;

            $mvc = explode('::', $mvc);

            if (count($mvc) == 3) {
                $module     = $mvc[0];
                $controller = $mvc[1];
                $action     = $mvc[2];
            } else {
                $module     = 'www';
                $controller = 'static';
                $action     = 'index';
            }

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

            $row = jdb(
                Config::get('application.i18n.db', SITE_NAME),
                Config::get('application.i18n.table', 'translation')
            )->where("key = $id")
            ->where('language = ' . lng())
            ->where('module = ' . $module)
            ->where('controller = ' . $controller)
            ->where('action = ' . $action)
            ->first(true);

            if ($row) {
                return static::assign($row->translation, $args);
            }

            return static::assign($default, $args);
        }

        public static function has($id, $lng)
        {
            $mvc = container()->getMvc();
            $mvc = empty($mvc) ? 'www::static::index' : $mvc;

            $mvc = explode('::', $mvc);

            if (count($mvc) == 3) {
                $module     = $mvc[0];
                $controller = $mvc[1];
                $action     = $mvc[2];
            } else {
                $module     = 'www';
                $controller = 'static';
                $action     = 'index';
            }

            $row = jdb(
                Config::get('application.i18n.db', SITE_NAME),
                Config::get('application.i18n.table', 'translation')
            )->where("key = $id")
            ->where('module = ' . $module)
            ->where('controller = ' . $controller)
            ->where('action = ' . $action)
            ->where('language = ' . $lng)
            ->first(true);

            return $row ? true : false;
        }

        public static function set($id, $lng, $translation)
        {
            $mvc = container()->getMvc();
            $mvc = empty($mvc) ? 'www::static::index' : $mvc;

            $mvc = explode('::', $mvc);

            if (count($mvc) == 3) {
                $module     = $mvc[0];
                $controller = $mvc[1];
                $action     = $mvc[2];
            } else {
                $module     = 'www';
                $controller = 'static';
                $action     = 'index';
            }

            return jdb(
                Config::get('application.i18n.db', SITE_NAME),
                Config::get('application.i18n.table', 'translation')
            )->create()
            ->setModule($module)
            ->setController($controller)
            ->setAction($action)
            ->setKey($id)
            ->setLanguage($lng)
            ->setTranslation($translation)
            ->save();
        }

        public static function forget($id, $lng)
        {
            return static::remove($id, $lng);
        }

        public static function remove($id, $lng)
        {
            $mvc = container()->getMvc();
            $mvc = empty($mvc) ? 'www::static::index' : $mvc;

            $mvc = explode('::', $mvc);

            if (count($mvc) == 3) {
                $module     = $mvc[0];
                $controller = $mvc[1];
                $action     = $mvc[2];
            } else {
                $module     = 'www';
                $controller = 'static';
                $action     = 'index';
            }

            $row = jdb(
                Config::get('application.i18n.db', SITE_NAME),
                Config::get('application.i18n.table', 'translation')
            )->where("key = $id")
            ->where('language = ' . $lng)
            ->where('module = ' . $module)
            ->where('controller = ' . $controller)
            ->where('action = ' . $action)
            ->first(true);

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
