<?php
    namespace Thin;

    class Prototype implements \ArrayAccess, \IteratorAggregate
    {
        /**
         * Prototypes built per class.
         *
         * @var array[string]Prototype
         */
        static protected $prototypes = [];

        /**
         * Pool of prototype methods per class.
         *
         * @var array[string]callable
         */
        static protected $pool;

        /**
         * Returns the prototype associated with the specified class or object.
         *
         * @param string|object $class Class name or instance.
         *
         * @return Prototype
         */
        static public function from($class)
        {
            if (is_object($class)) {
                $class = get_class($class);
            }

            if (empty(self::$prototypes[$class])) {
                self::$prototypes[$class] = new static($class);
            }

            return self::$prototypes[$class];
        }

        static public function configure(array $config)
        {
            self::$pool = $config;

            foreach (self::$prototypes as $class => $prototype) {
                $prototype->consolidatedMethods = null;

                if (empty($config[$class])) {
                    continue;
                }

                $prototype->methods = array_merge($prototype->methods, $config[$class]);
            }
        }

        /**
         * Class associated with the prototype.
         *
         * @var string
         */
        protected $class;

        /**
         * Parent prototype.
         *
         * @var Prototype
         */
        protected $parent;

        /**
         * Methods defined by the prototype.
         *
         * @var array[string]callable
         */
        protected $methods = [];

        /**
         * Methods defined by the prototypes chain.
         *
         * @var array[string]callable
         */
        protected $consolidatedMethods;

        /**
         * Creates a prototype for the specified class.
         *
         * @param string $class
         */
        protected function __construct($class)
        {
            $this->class = $class;

            $parent_class = get_parent_class($class);

            if ($parent_class) {
                $this->parent = static::from($parent_class);
            }

            if (isset(self::$pool[$class])) {
                $this->methods = self::$pool[$class];
            }
        }

        /**
         * Consolidate the methods of the prototype.
         *
         * The method creates a single array from the prototype methods and those of its parents.
         *
         * @return array[string]callable
         */
        protected function getConsolidatedMethods()
        {
            if ($this->consolidatedMethods !== null) {
                return $this->consolidatedMethods;
            }

            $methods = $this->methods;

            if ($this->parent) {
                $methods += $this->parent->getConsolidatedMethods();
            }

            return $this->consolidatedMethods = $methods;
        }

        /**
         * Revokes the consolidated methods of the prototype.
         *
         * The method must be invoked when prototype methods are modified.
         */
        protected function revokeConsolidatedMethods()
        {
            $class = $this->class;

            foreach (self::$prototypes as $prototype) {
                if (!is_subclass_of($prototype->class, $class)) {
                    continue;
                }

                $prototype->consolidatedMethods = null;
            }

            $this->consolidatedMethods = null;
        }

        /**
         * Adds or replaces the specified method of the prototype.
         *
         * @param string $method The name of the method.
         *
         * @param callable $callback
         */
        public function offsetSet($method, $callback)
        {
            self::$prototypes[$this->class]->methods[$method] = $callback;

            $this->revokeConsolidatedMethods();
        }

        /**
         * Removed the specified method from the prototype.
         *
         * @param string $method The name of the method.
         */
        public function offsetUnset($method)
        {
            unset(self::$prototypes[$this->class]->methods[$method]);

            $this->revokeConsolidatedMethods();
        }

        /**
         * Checks if the prototype defines the specified method.
         *
         * @param string $method The name of the method.
         *
         * @return bool
         */
        public function offsetExists($method)
        {
            $methods = $this->getConsolidatedMethods();

            return isset($methods[$method]);
        }

        /**
         * Returns the callback associated with the specified method.
         *
         * @param string $method The name of the method.
         *
         * @throws Exception if the method is not defined.
         *
         * @return callable
         */
        public function offsetGet($method)
        {
            $methods = $this->getConsolidatedMethods();

            if (!isset($methods[$method])) {
                throw new Exception("$method, $this->class");
            }

            return $methods[$method];
        }

        /**
         * Returns an iterator for the prototype methods.
         */
        public function getIterator()
        {
            $methods = $this->getConsolidatedMethods();

            return new \ArrayIterator($methods);
        }
    }
