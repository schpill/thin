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

        public static function clean()
        {
            $oldies = jmodel('apiauth')->where('expire < ' . time())->exec(true);

            if ($oldies) {
                $oldies->delete();
            }
        }

        public static function check($resourceApi)
        {
            $token = request()->getToken();

            if (is_null($token)) {
                header("HTTP/1.0 401 Unauthorized");

                self::renderJson([
                    'status' => 401,
                    'message' => 'Unauthorized'
                ]);
            }

            $auth = jmodel('apiauth')->where('token = ' . $token)->first(true);

            if (empty($auth)) {
                header("HTTP/1.0 401 Unauthorized");

                self::renderJson([
                    'status' => 401,
                    'message' => 'Unauthorized'
                ]);
            } else {
                $expire = $auth->expire;

                if (time() > $expire) {
                    header("HTTP/1.0 401 Unauthorized");

                    self::renderJson([
                        'status' => 401,
                        'message' => 'Unauthorized'
                    ]);
                }

                $resource = $auth->resource;

                if ($resource != $resourceApi) {
                    header("HTTP/1.0 401 Unauthorized");

                    self::renderJson([
                        'status' => 401,
                        'message' => 'Unauthorized'
                    ]);
                }

                return $auth;
            }
        }

        public static function can($auth, $resource, $action)
        {
            if (empty($auth)) {
                self::renderJson([
                    'status' => 401,
                    'message' => 'Unauthorized'
                ]);
            }

            $rigth = jmodel('apiright')
            ->where('resource = feed')
            ->where('action = item')
            ->where('user_id = ' . $auth->user_id)
            ->first(true);

            if (empty($right)) {
                self::renderJson([
                    'status' => 401,
                    'message' => 'Unauthorized'
                ]);
            }

            $can = $right->can;

            if (2 == $can) {
                self::renderJson([
                    'status' => 401,
                    'message' => 'Unauthorized'
                ]);
            }
        }

        private static function renderJson(array $data)
        {
            header('content-type: application/json; charset=utf-8');

            die(json_encode($data));
        }
    }
