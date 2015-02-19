<?php
    namespace Thin\Filter;
    class Alphanumeric extends \Thin\Filter
    {
        /**
         * Whether to allow alphabetic characters
         * @var boolean
         */
        private $allowAlphabetic = true;

        /**
         * Whether to allow numeric characters
         * @var boolean
         */
        private $allowNumeric = true;

        /**
         * Strip all non-alphabetic and/or non-numeric characters
         *
         * @param string $value The input string
         *
         * @return string Filtered string
         *
         *
         */
        public function filter($value)
        {
            $regex = '/[\W_]/u';

            if($this->allowAlphabetic && !$this->allowNumeric) {
                    $regex = '/[\W_\d]/u';
            } else if($this->allowNumeric && !$this->allowAlphabetic) {
                    $regex = '/[\D]/u';
            } else if(!$this->allowAlphabetic && !$this->allowNumeric) {
                    return '';
            }

            return preg_replace($regex, '', $value);
        }

        /**
         * Check whether alphabetic characters are allowed
         *
         * @return boolean
         *
         *
         */
        public function getAllowAlphabetic()
        {
            return $this->allowAlphabetic;
        }

        /**
         * Set alphabetic character allowing
         *
         * @param boolean $allowAlphabetic
         *
         * @return \Thin\Filter\AlphaNumeric Provides a fluent interface
         *
         *
         */
        public function setAllowAlphabetic($allowAlphabetic)
        {
            $this->allowAlphabetic = (boolean) $allowAlphabetic;
            return $this;
        }

        /**
         * Check whether digits are allowed
         *
         * @return boolean
         *
         *
         */
        public function getAllowNumeric()
        {
            return $this->allowNumeric;
        }

        /**
         * Set digit allowing
         *
         * @param boolean $allowNumeric
         *
         * @return \Thin\Filter\AlphaNumeric Provides a fluent interface
         *
         *
         */
        public function setAllowNumeric($allowNumeric)
        {
            $this->allowNumeric =(boolean)$allowNumeric;
            return $this;
        }
    }
