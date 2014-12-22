<?php
    namespace Thin;

    class Core
    {
        public function __construct()
        {
            throw new Exception('The class core must be used with static methods.');
        }

        public function __clone()
        {
            throw new Exception('The class core must be used with static methods.');
        }

        public static function instance()
        {
            return Fly::instance('app');
        }

        public static function __callStatic($fn, $args)
        {
            $instance = Fly::instance('app');

            return call_user_func_array([$instance, $fn], $args);
        }
    }
