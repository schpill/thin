<?php
    namespace Thin;

    class Iterator extends \ArrayIterator
    {
        private $data;

        /**
         * Constructor
         *
         * @param array $data Data over which to iterate
         */
        public function __construct($data)
        {
            $this->data = $data;
        }

        /**
         * Return the current element
         *
         * @return mixed Can return any type.
         */
        public function current()
        {
            return current($this->data);
        }

        /**
         * Move forward to next element
         *
         * @return void Any returned value is ignored.
         *
         * @since 0.0.0-dev
         */
        public function next()
        {
            next($this->data);
        }

        /**
         * Return the key of the current element
         *
         * @return string|int scalar on success, integer 0 on failure.
         */
        public function key()
        {
            $key = key($this->data);
            if (empty($key) && $key !== '0') {
                return 0;
            } else {
                return $key;
            }
        }

        /**
         * Checks if current position is valid
         *
         * @return boolean The return value will be casted to boolean and then evaluated.<br>
         * Returns true on success or false on failure.
         */
        public function valid()
        {
            $key = key($this->data);
            return $key !== null && $key !== false;
        }

        /**
         * Rewind the Iterator to the first element
         *
         * @return void Any returned value is ignored.
         */
        public function rewind()
        {
            reset($this->data);
        }
    }
