<?php
    /**
     * Auth class
     * @author      Gerald Plusquellec
     */

    namespace Thin;
    class Auth
    {
        /**
         * The user currently being managed by the driver.
         *
         * @var mixed
         */
        public $user;

        /**
         * The current value of the user's token.
         *
         * @var string|null
         */
        public $token;

        public function __construct()
        {
            $this->token = Session::instance('ThinSession')->getAuthToken();
            if (is_null($this->token)) {
                $this->token = Utils::token();
                Session::instance('ThinSession')->setAuthToken($this->token);
            }
        }

        public function check()
        {
            return !is_null($this->user);
        }

        public function gest()
        {
            return is_null($this->user);
        }

        public function login($user)
        {
            $this->user = $user;
            Session::instance('ThinSession')->setAuthUser(array($user->getId() => $this->token));
        }

        public function logout($redirect = null)
        {
            unset($this->user);
            unset($this->token);
            Session::instance('ThinSession')->forgetAuthToken();
            Session::instance('ThinSession')->forgetAuthUser();
            if (null !== $redirect) {
                Utils::redirect($redirect);
            }
        }
    }
