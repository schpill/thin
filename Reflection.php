<?php
    namespace Thin;
    use ReflectionClass;

    /**
     * Simple class that Instantiates reflections of classes.
     *
     * @package Thin
     */
    class Reflection extends Singleton
    {
        /**
         * Current reflections.
         *
         * @var array
         */
        private $reflections = array();

        /**
         * Instantiates a new ReflectionClass for the given class.
         *
         * @param string $class Name of a class
         * @return Reflections $this so you can chain calls like Reflections::instance()->add('class')->get()
         */
        public function add($class = null)
        {
            $class = $this->getClass($class);

            if (!Arrays::exists($class, $this->reflections)) {
                $this->reflections[$class] = new ReflectionClass($class);
            }

            return $this;
        }

        /**
         * Destroys the Instantiated ReflectionClass.
         *
         * Put this here mainly for testing purposes.
         *
         * @param string $class Name of a class.
         * @return void
         */
        public function destroy($class)
        {
            if (Arrays::exists($class, $this->reflections)) {
                $this->reflections[$class] = null;
            }
        }

        /**
         * Get an Instantiated ReflectionClass.
         *
         * @param string $class Optional name of a class
         * @return mixed null or a ReflectionClass instance
         * @throws Exception if class was not found
         */
        public function get($class = null)
        {
            $class = $this->getClass($class);

            if (Arrays::exists($class, $this->reflections)) {
                return $this->reflections[$class];
            }

            throw new Exception("Class not found: $class");
        }

        /**
         * Retrieve a class name to be reflected.
         *
         * @param mixed $mixed An object or name of a class
         * @return string
         */
        private function getClass($mixed = null)
        {
            if (is_object($mixed)) {
                return get_class($mixed);
            }

            if (!is_null($mixed)) {
                return $mixed;
            }

            return $this->getCalledClass();
        }
    }
