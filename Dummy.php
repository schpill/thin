<?php
    namespace Thin;

    class Dummy
    {
        public $instance;

        public function __construct($class, $args = array())
        {
            $this->instance = new $class($args);
        }

    }
