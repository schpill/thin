<?php
    namespace Thin;

    class Apifb
    {
        private $entity;
        private $token;
        private $dbs = array();

        public function __construct($entity, $login, $password, $factory = 'Memorydb')
        {
            $this->dbs['entity']    = new $factory($entity);
            $this->dbs['user']      = new $factory('api_db_user');
            $this->dbs['right']     = new $factory('api_db_right');

            $user = $this->dbs['user']->where("login = $login")->where("password = " . sha1($password))->exec();
            if (count($user)) {

            }
        }
    }
