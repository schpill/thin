<?php
    namespace Thin\Html;

    class Flash implements \Countable, \IteratorAggregate
    {
        /**
         * Clear the message buffer
         *
         * @return \Thin\Html\Flash Provides a fluent interface
         *
         *
         */
        public function clear()
        {
            $_SESSION['ThinFlash'] = array();
            return $this;
        }

        /**
         * Fetch all messages from the message buffer
         *
         * @return array
         *
         *
         */
        public function get()
        {
            if(ake('ThinFlash', $_SESSION)) {
                return $_SESSION['ThinFlash'];
            } else {
                return array();
            }
        }

        /**
         * Check whether the message buffer is empty
         *
         * @return boolean
         *
         *
         */
        public function isEmpty()
        {
            if(ake('ThinFlash', $_SESSION)) {
                return(count($_SESSION['ThinFlash']) == 0);
            } else {
                return true;
            }
        }

        /**
         * Append a new message to the message buffer
         *
         * @param mixed $message The message to append
         *
         * @return \Thin\Html\Flash Provides a fluent interface
         *
         *
         */
        public function add($message)
        {
            if(!ake('ThinFlash', $_SESSION))
            {
                $_SESSION['ThinFlash'] = array();
            }
            $_SESSION['ThinFlash'][] = $message;

            return $this;
        }

        /**
         * Count the messages in the message buffer
         *
         * @return int
         *
         *
         */
        public function count()
        {
            if(ake('ThinFlash', $_SESSION)) {
                return count($_SESSION['ThinFlash']);
            } else {
                return 0;
            }
        }

        /**
         * Allow iterating over the message buffer using a foreach loop
         *
         * @return \Thin\Iterator
         *
         *
         */
        public function getIterator()
        {
            return new \Thin\Iterator($this->get());
        }
    }
