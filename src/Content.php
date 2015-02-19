<?php
    namespace Thin;

    class Content
    {
        private static $items = array();

        public static function start()
        {
            ob_start();
        }

        public static function stop($back = false)
        {
            $content = ob_get_contents();
            ob_end_clean();

            if (true === $back) {
                return $content;
            }

            ob_end_flush();
        }

        public static function load($file, $args = array(), $back = true)
        {
            if (File::exists($file)) {
                static::start();
                $code = static::parse(File::get($file), $args);

                eval(' ?>' . $code . '<?php ');
                $content = static::stop(true);

                if (true === $back) {
                    return $content;
                }

                echo $content;
            }
        }

        private static function parse($code, $vars)
        {
            $code = str_replace(array('{{', '}}'), array('<?php', '?>'), $code);

            if (count($vars)) {
                foreach ($vars as $var) {

                    if (!isset($$var)) {
                        $value = static::get($var, false);

                        if (false === $value) {
                            $value = Config::get($var, false);

                            if (false === $value) {
                                $value = isAke($_REQUEST, $var, false);

                                if (false === $value) {
                                    $value = '';
                                }
                            }
                        }
                    } else {
                        $value = $$var;
                    }

                }
            }

            return $code;
        }

        public static function get($key, $default = null)
        {
            return arrayGet(static::$items, $key, $default);
        }

        public static function set($key, $value = null)
        {
            static::$items = arraySet(static::$items, $key, $value);
        }

        public static function has($key)
        {
            return !is_null(static::get($key));
        }

        public static function forget($key)
        {
            if (static::has($key)) {
                arrayUnset(static::$items, $key);
            }
        }
    }
