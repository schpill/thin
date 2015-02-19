<?php
    namespace Thin;

    class Input
    {
        public static function __callStatic($method, $args)
        {
            $auth = ['GET', 'POST', 'COOKIE', 'SESSION', 'SERVER', 'REQUEST', 'GLOBALS'];

            $method = Inflector::upper($method);

            if (Arrays::in($method, $auth) && count($args) > 0) {
                $default = isset($args[1]) ? $args[1] : null;

                return isAke(self::tab($method), Arrays::first($args), $default);
            } elseif (Arrays::in($method, $auth) && count($args) == 0) {
                return self::tab($method);
            } else {
                throw new Exception("Wrong parameters.");
            }
        }

        private static function tab($method)
        {
            switch ($method) {
                case 'COOKIE':
                    return $_COOKIE;
                case 'GET':
                    return $_GET;
                case 'POST':
                    return $_POST;
                case 'SESSION':
                    return $_SESSION;
                case 'SERVER':
                    return $_SERVER;
                case 'REQUEST':
                    return $_REQUEST;
                case 'GLOBALS':
                    return $GLOBALS;
            }
        }
    }
