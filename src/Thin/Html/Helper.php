<?php
    namespace Thin\Html;
    class Helper
    {
        public static function display($str)
        {
            return stripslashes(\Thin\Inflector::utf8($str));
        }

        public static function date($date, $format = 'd/m/y')
        {
            if (!empty($date) && is_numeric($date)) {
                $date = date($format, $date);
            }
            return $date;
        }

        public static function displayTest($str)
        {
            $str = static::display($str);
            $str = repl('_', ' ', $str);
            return stripslashes($str);
        }

        public static function js($str)
        {
            $str = static::display($str);
            return json_encode($str);
        }

        public static function displayError($str)
        {
            $str = static::display($str);
            $str = repl('is ', 'is not ', $str);
            return $str;
        }

        public static function markValue($str)
        {
            $str = repl(" '", ' <span style="padding: 5px; font-family: Coustard; color: #fff; background: #4e1078;">', $str);
            $str = repl("'", ' </span>', $str);
            return $str;
        }

        public static function getRate($ref, $comp)
        {
            if (1 > $ref || 1 > $comp) {
                return 0;
            }

            return round(($comp / $ref * 100), 2);
        }
    }
