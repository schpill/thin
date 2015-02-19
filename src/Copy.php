<?php
    namespace Thin;

    use ReflectionClass;

    class Copy
    {
        private static $__instance;
        public static $__class = 'stdClass';
        public static $__args = [];

        /**
         * Get the underlying instance.
         *
         * We'll always cache the instance and reuse it.
         *
         */
        public static function __instance()
        {
            if (empty(self::$__instance)) {
                self::$__instance = with(new \ReflectionClass(self::$__class))->newInstanceArgs(self::$__args);
            }

            return self::$__instance;
        }

        /**
         * Reset the underlying copy instance.
         *
         */
        public static function __reset()
        {
            self::$__instance = null;

            return self::__instance();
        }

        /**
         * Handle dynamic, static calls to the object.
         *
         * @codeCoverageIgnore
         *
         * @param string $method    The method name.
         * @param array  $args The arguments.
         *
         * @return mixed
         */
        public static function __callStatic($method, $args)
        {
            switch (count($args)) {
                case 0:
                    return self::__instance()->$method();
                case 1:
                    return self::__instance()->$method($args[0]);
                case 2:
                    return self::__instance()->$method($args[0], $args[1]);
                case 3:
                    return self::__instance()->$method($args[0], $args[1], $args[2]);
                case 4:
                    return self::__instance()->$method($args[0], $args[1], $args[2], $args[3]);
                case 5:
                    return self::__instance()->$method($args[0], $args[1], $args[2], $args[3], $args[4]);
                default:
                    return call_user_func_array([self::__instance(), $method], $args);
            }
        }
    }
