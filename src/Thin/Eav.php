<?php
    namespace Thin;
    class Eav
    {
        protected $_db;

        public function __construct()
        {
            $this->_db = new Memory('Thin', 'Eav');
        }

        public function save()
        {
            $this->_db->save();
            $this->_db = new Memory('Thin', 'Eav');
            return $this;
        }

        public function select($entity)
        {
            $this->_db->where('entity = ' . $entity);
            return $this;
        }

        public function results()
        {
            return $this->_db->_getResults();
        }

        public function __call($method, $args)
        {
            $this->_db->$method(current($args));
            return $this;
        }
    }
