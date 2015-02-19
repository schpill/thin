<?php
    namespace Thin;
    class Unstatic
    {
        private $static;
        private $result = null;

        public function __construct($static)
        {
            $this->static = $static;
        }

        public function __call($method, $parameters)
        {
            if (count($parameters)) {
                if (!empty($this->result))  {
                    array_unshift($parameters, $this->result);
                }
            } else {
                $parameters = array($this->result);
            }
            $this->result =  call_user_func_array(array("Thin\\" . $this->static, $method), $parameters);
            return $this;
        }

        public function __toString()
        {
            return $this->result;
        }
    }
