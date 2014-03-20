<?php
    namespace Thin;

    class Bag implements \ArrayAccess, \Countable, \IteratorAggregate
    {
        /**
         * Key-value array of arbitrary values
         * @var array
         */
        protected $values = array();

        /**
         * Constructor
         * @param array $items Pre-populate set with this key-value array
         */
        public function __construct($items = array())
        {
            $this->replace($items);
        }

        /**
         * Normalize data key
         *
         * Used to transform data key into the necessary
         * key format for this set. Used in subclasses
         *
         * @param  string $key The data key
         * @return mixed       The transformed/normalized data key
         */
        protected function normalizeKey($key)
        {
            return $key;
        }

        /**
         * Set data key to value
         * @param string $key   The data key
         * @param mixed  $value The data value
         */
        public function set($key, $value)
        {
            $this->values[$this->normalizeKey($key)] = $value;
            return $this;
        }

        /**
         * Get values value with key
         * @param  string $key     The values key
         * @param  mixed  $default The value to return if values key does not exist
         * @return mixed           The values value, or the default value
         */
        public function get($key, $default = null)
        {
            if ($this->has($key)) {
                $isInvokable = is_object($this->values[$this->normalizeKey($key)]) && method_exists($this->values[$this->normalizeKey($key)], '__invoke');

                return $isInvokable
                ? $this->values[$this->normalizeKey($key)]($this)
                : $this->values[$this->normalizeKey($key)];
            }

            return $default;
        }

        /**
         * Add values to set
         * @param array $items Key-value array of values to append to this set
         */
        public function replace($items)
        {
            foreach ($items as $key => $value) {
                $this->set($key, $value); // Ensure keys are normalized
            }
        }

        /**
         * Fetch set values
         * @return array This set's key-value values array
         */
        public function all()
        {
            return $this->values;
        }

        /**
         * Fetch set values keys
         * @return array This set's key-value values array keys
         */
        public function keys()
        {
            return array_keys($this->values);
        }

        /**
         * Does this set contain a key?
         * @param  string  $key The values key
         * @return boolean
         */
        public function has($key)
        {
            return Arrays::exists($this->normalizeKey($key), $this->values);
        }

        /**
         * Remove value with key from this set
         * @param  string $key The values key
         */
        public function remove($key)
        {
            unset($this->values[$this->normalizeKey($key)]);
            return $this;
        }

        /**
         * Property Overloading
         */

        public function __get($key)
        {
            return $this->get($key);
        }

        public function __set($key, $value)
        {
            $this->set($key, $value);
        }

        public function __isset($key)
        {
            return $this->has($key);
        }

        public function __unset($key)
        {
            return $this->remove($key);
        }

        /**
         * Clear all values
         */
        public function clear()
        {
            $this->values = array();
        }

        /**
         * Array Access
         */

        public function offsetExists($offset)
        {
            return $this->has($offset);
        }

        public function offsetGet($offset)
        {
            return $this->get($offset);
        }

        public function offsetSet($offset, $value)
        {
            $this->set($offset, $value);
        }

        public function offsetUnset($offset)
        {
            $this->remove($offset);
        }

        /**
         * Countable
         */

        public function count()
        {
            return count($this->values);
        }

        /**
         * IteratorAggregate
         */

        public function getIterator()
        {
            return new \ArrayIterator($this->values);
        }

        /**
         * Ensure a value or object will remain globally unique
         * @param  string  $key   The value or object name
         * @param  Closure        The closure that defines the object
         * @return mixed
         */
        public function singleton($key, $value)
        {
            $this->set($key, function ($c) use ($value) {
                static $object;

                if (null === $object) {
                    $object = $value($c);
                }

                return $object;
            });
        }

        /**
         * Protect closure from being directly invoked
         * @param  Closure $callable A closure to keep from being invoked and evaluated
         * @return Closure
         */
        public function protect(\Closure $callable)
        {
            return function () use ($callable) {
                return $callable;
            };
        }
    }
