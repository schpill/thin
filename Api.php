<?php
    namespace Thin;
    class Api
    {
        private $resource, $token;

        public function __construct($resource)
        {
            $this->resource = $resource;
        }

        public static function instance($resource)
        {
            $key    = sha1(serialize(func_get_args()));
            $has    = Instance::has('Api', $key);
            if (true === $has) {
                return Instance::get('Api', $key);
            } else {
                return Instance::make('Api', $key, with(new self($resource)));
            }
        }

        public function auth($key)
        {
            $db = em('core', 'api');
            $auth = $db->where('resource = ' . $this->resource)->where('key = ' . $key)->first(true);
            if (!empty($auth)) {
                $this->token = sha1(serialize($auth->assoc()) . date('dmY'));
                $auth->setToken($this->token)->save();
            }
            return $this->isAuth();
        }

        public function isAuth()
        {
            return !is_null($this->token);
        }
    }
