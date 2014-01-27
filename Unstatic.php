<?php
    namespace Thin;
    class Unstatic
    {
        private $static;

        public function __construct($static)
        {
            $this->static = $static;
        }

        public function __call($method, $parameters)
        {
            return  call_user_func_array(array("Thin\\" . $this->static, $method), $parameters);
        }
    }
