<?php
    namespace Thin;

    class Serviceprovider
    {
        /**
         * Contains all known resources
         *
         * @var array
         */
        protected static $resources = [];

        /**
         * Adds a new resource to the Services provider.
         *
         * @param string $name
         * @param mixed  $constructor
         *
         * @return $this
         */
        public function register($name, $constructor)
        {
            self::$resources[$name] = $constructor;

            return $this;
        }

        /**
         * Attempts to return the named resource to a valid class name.
         *
         * @param string $name
         *
         * @return void
         *
         * @throws Exception
         */
        public function resolve($name)
        {
            if (!isset(self::$resources[$name])) {
                throw new Exception("[$name] is not a known resource.");
            }

            return isAke(self::$resources, $name);
        }

        public static function __callStatic($method, $args)
        {
            if (!isset(self::$resources[$method])) {
                throw new Exception("[$name] is not a known resource.");
            }

            return isAke(self::$resources, $method);
        }
    }
