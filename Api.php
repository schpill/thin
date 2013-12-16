<?php
    namespace Thin;
    class Api
    {
        public $token;

        public function auth($resource, $key)
        {
            if (empty($resource) || empty($key)) {
                return false;
            }
            $res = Data::query('apikey', 'resource = ' . $resource . ' && key = ' . $key);
            if (1 == count($res)) {
                $ever = Data::getObject(current($res));
                $this->token = sha1($resource . $key);
            }
            return $this->isAuth();
        }

        public function isAuth()
        {
            return !is_null($this->token);
        }
    }
