<?php
    namespace Thin;

    class Filter
    {
        public static function clean($data)
        {
            if (Arrays::is($data)) {
                foreach ($data as $key => $value) {
                    unset($data[$key]);
                    $data[static::clean($key)] = static::clean($value);
                }
            } else {
                if (ini_get('magic_quotes_gpc')) {
                    $data = stripslashes($data);
                } else {
                    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                }
            }
            return $data;
        }

        public static function sanitize($string, $trim = false, $int = false, $str = false)
        {
            $string = filter_var($string, FILTER_SANITIZE_STRING);
            $string = trim($string);
            $string = stripslashes($string);
            $string = strip_tags($string);
            $string = str_replace(
                array(
                    '‘',
                    '’',
                    '“',
                    '”'
                ),
                array(
                    "'",
                    "'",
                    '"',
                    '"'
                ),
                $string
            );

            if ($trim) $string  = substr($string, 0, $trim);
            if ($int) $string   = preg_replace("/[^0-9\s]/", "", $string);
            if ($str) $string   = preg_replace("/[^a-zA-Z\s]/", "", $string);

            return $string;
        }
    }
