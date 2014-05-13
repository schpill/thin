<?php
    namespace Thin;

    /**
     * An other vision of this pattern
     * @package Thin
     */

    abstract class Singleton
    {
        /**
         * Array of cached singleton objects.
         *
         * @var array
         */
        private static $instances = array();

        /**
         * Static method for instantiating a singleton object.
         *
         * @return object
         */
        final public static function instance()
        {
            $className = get_called_class();

            if (!Arrays::exists(static::$instances[$className])) {
                static::$instances[$className] = new $className(func_get_args());
            }

            return static::$instances[$className];
        }

        /**
         * Singleton objects should not be cloned.
         *
         * @return void
         */
        final private function __clone()
        {
            throw new Exception('Clone a singleton is forbidden.');
        }

        /**
         * Similar to a get_called_class() for a child class to invoke.
         *
         * @return string
         */
        final protected function getCalledClass()
        {
            $backtrace = debug_backtrace();
            return get_class($backtrace[2]['object']);
        }
    }
