<?php
    namespace Thin\Filter;
    class Stripnewlines extends \Thin\Filter
    {
        /**
         * Strip all newline characters and convert them to spaces
         *
         * @param string $value The input string
         *
         * @return string Filtered string
         */
        public function filter ($value)
        {
            // replace Windows line endings
            $value = repl("\r\n"," ",$value);
            // replace Unix line endings
            $value = repl("\n"," ",$value);
            return $value;
        }
    }
