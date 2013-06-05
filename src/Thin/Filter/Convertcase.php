<?php
    namespace Thin\Filter;
    class Convertcase extends \Thin\Filter
    {
        /**
         * The case value
         * @var int
         */
        private $case = MB_CASE_TITLE;

        /**
         * convert a string's case
         *
         * @param string $value The input string
         *
         * @return string Filtered string
         *
         *
         */
        public function filter($value)
        {
            return mb_convert_case($value, $this->getCase(), 'utf8');
        }

        /**
         * Fetch the currently defined case.
         *
         * @return int The currently defined case
         *
         *
         */
        public function getCase()
        {
            return $this->case;
        }

        /**
         * Set the case that should be used then converting case.
         *
         * @param int $case The case value.<br>
         * It is recommended that the MB_CASE_* strings are used here.
         *
         * @return \Thin\Filter\ConvertCase Provides a fluent interface
         *
         *
         */
        public function setCase($case)
        {
            $this->case = intval($case);
            return $this;
        }
    }
