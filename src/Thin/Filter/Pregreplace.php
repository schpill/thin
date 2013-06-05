<?php
    namespace Thin\Filter;
    class Pregreplace extends \Thin\Filter
    {
        /**
         * The regex
         * @var string
         */
        private $match;

        /**
         * Replacement string
         * @var string
         */
        private $replacement;

        /**
         * Construct the filter
         *
         * @param string $match The regex
         * @param string $replacement Replacement string
         */
        public function __construct($match = '/^$/', $replacement = '')
        {
            $this->match = strval($match);
            $this->replacement = strval($replacement);
        }

        /**
         * Run the regex replacement
         *
         * @param string $value
         *
         * @return string
         */
        public function filter($value)
        {
            return preg_replace($this->match, $this->replacement, $value);
        }
    }
