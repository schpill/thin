<?php
    namespace Thin\Filter;
    class Trim extends \Thin\Filter {
        /**
         * Characters to be trimmed
         * @var string
         */
        private $chars;

        /**
         * Trimming type to be used(left, right, normal)
         * @var string
         */
        private $type;

        /**
         * Instantiate the filter
         *
         * @param string $chars [optional] Characters to be trimmed
         * @param string $type [optional] Trimming type to be used(left, right, normal)
         *
         *
         */
        public function __construct($chars = null, $type = "normal")
        {
            $this->chars = $chars;
            $this->type = $type;
        }

        /**
         * Trim the string
         *
         * @param string $value The input string
         *
         * @return string Filtered string
         *
         *
         */
        public function filter($value)
        {
            // perform trimming according to the specified criteria
            switch($this->type) {
                case "left":
                    $value = ltrim($value, $this->chars);
                    break;
                case "right":
                    $value = rtrim($value,$this->chars);
                    break;
                default:
                    $value = trim($value,$this->chars);
                    break;
            }

            // we're left with the filtered value
            return $value;
        }

        /**
         * Set the charlist for the trim function
         *
         * @param string $chars
         *
         * @return \Thin\Filter\Trim Provides a fluent interface
         *
         *
         */
        public function setChars($chars = null)
        {
            $this->chars = $chars;
            return $this;
        }

        /**
         * Set the trimming type(left, right or normal)
         *
         * @param string $type
         *
         * @return \Thin\Filter\Trim Provides a fluent interface
         *
         *
         */
        public function setType($type = "normal")
        {
            $this->type = $type;
            return $this;
        }
    }
