<?php

    namespace Thin;

    /**
     * Container for a set of options.
     *
     * @author Gérald Plusquellec
     */
    class Options implements \IteratorAggregate, \ArrayAccess
    {
        /**
         * @var array
         */
        protected $options = array();

        /**
         * Constructor.
         *
         * @param array $options
         */
        public function __construct($options = array())
        {
            $this->options = $options;
        }

        /**
         * Retrieve an iterator for entries.
         *
         * @return \ArrayIterator
         */
        public function getIterator()
        {
            return new \ArrayIterator($this->options);
        }

        /**
         * Add new entry in the options.
         * Do not use array_merge as it rewrites the keys.
         *
         * @param array $options
         */
        public function add(array $options)
        {
            if (false == empty($options)) {
                $this->options = $this->options + $options;
            }
        }

        /**
         * Return every entries.
         *
         * @return array
         */
        public function all()
        {
            return $this->options;
        }

        /**
         * Clear the options.
         */
        public function clear()
        {
            $this->options = array();
        }

        /**
         * Return an entry.
         *
         * @param $name
         * @param  null       $default
         * @return null|mixed
         */
        public function get($name, $default = null)
        {
            if ($this->has($name)) {
                return $this->options[$name];
            }

            return $default;
        }

        /**
         * Check that an entry exists.
         *
         * @param $name
         * @return bool
         */
        public function has($name)
        {
            return Arrays::exists($name, $this->options) && false === empty($this->options[$name]) ;
        }

        /**
         * Merge options with existing one.
         *
         * @param array $options
         */
        public function merge(array $options)
        {
            $this->options = array_merge($this->options, $options);
        }

        /**
         * Remove an entry of the options.
         *
         * @param $name
         */
        public function remove($name)
        {
            if ($this->has($name)) {
                unset($this->options[$name]);
            }
        }

        /**
         * Set an entry.
         *
         * @param $name
         * @param $value
         */
        public function set($name, $value)
        {
            $this->options[$name] = $value;
        }

        /**
         * Whether a offset exists
         *
         * @param mixed $offset An offset to check for.
         *
         * @return boolean true on success or false on failure.
         */
        public function offsetExists($offset)
        {
            return $this->has($offset);
        }

        /**
         * Offset to retrieve
         *
         * @param mixed $offset The offset to retrieve.
         *
         * @return mixed Can return all value types.
         */
        public function offsetGet($offset)
        {
            return $this->get($offset);
        }

        /**
         * Offset to set
         *
         * @param mixed $offset The offset to assign the value to.
         * @param mixed $value  The value to set.
         *
         * @return void
         */
        public function offsetSet($offset, $value)
        {
            $this->set($offset, $value);
        }

        /**
         * Offset to unset
         *
         * @param mixed $offset The offset to unset.
         *
         * @return void
         */
        public function offsetUnset($offset)
        {
            $this->remove($offset);
        }
    }
