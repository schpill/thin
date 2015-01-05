<?php
    namespace Thin;

    use Facebook\GraphUser;
    use Facebook\FacebookRequest;
    use Facebook\FacebookSession;
    use Facebook\FacebookRedirectLoginHelper;

    class Facebook {

        /**
         * @var Store
         */
        protected $session;

        /**
         * @var Repository
         */
        protected $config;

        /**
         * @var null
         */
        protected $appId;

        /**
         * @var null
         */
        protected $appSecret;

        /**
         * @var null
         */
        protected $redirectUrl;

        /**
         * @param Repository $config
         * @param null $appId
         * @param null $appSecret
         * @param null $redirectUrl
         */
        public function __construct($config, $appId = null, $appSecret = null, $redirectUrl = null)
        {
            $this->session      = session('facebook');
            $this->config       = $config;
            $this->appId        = $appId;
            $this->appSecret    = $appSecret;
            $this->redirectUrl  = $redirectUrl;

            FacebookSession::setDefaultApplication($this->appId, $this->appSecret);

            if (!getenv('FACEBOOK_TESTING')) {
                $this->start();
            }
        }

        /**
         * Start the native php session. Require by facebook.
         *
         * @return void
         */
        public function start()
        {
            session_start();
        }

        /**
         * Getter for "session".
         *
         * @return \thin\Session\
         */
        public function getSession()
        {
            return $this->session;
        }

        /**
         * Getter for "config".
         *
         */
        public function getConfig()
        {
            return $this->config;
        }

        /**
         * Get redirect url.
         *
         * @return string
         */
        public function getRedirectUrl()
        {
            return $this->redirectUrl ?: isAke($this->config, 'redirect_url', '/');
        }

        /**
         * Set new redirect url.
         *
         * @param string $url
         * @return $this
         */
        public function setRedirectUrl($url)
        {
            $this->redirectUrl = $url;

            return $this;
        }

        /**
         * Get Facebook Redirect Login Helper.
         *
         * @return FacebookRedirectLoginHelper
         */
        public function getFacebookHelper()
        {
            $redirectHelper = new FacebookRedirectLoginHelper(
                $this->getRedirectUrl(),
                $this->appId,
                $this->appSecret
            );

            $redirectHelper->disableSessionStatusCheck();

            return $redirectHelper;
        }

        /**
         * Get AppId.
         *
         * @return string
         */
        public function getAppId()
        {
            return $this->appId;
        }

        /**
         * Get AppSecret.
         *
         * @return string
         */
        public function getAppSecret()
        {
            return $this->appSecret;
        }

        /**
         * Set current appId (runtime mode).
         *
         * @param    string $appId
         * @return   self
         */
        public function setAppId($appId)
        {
            $this->appId = $appId;

            return $this;
        }

        /**
         * Set current appSecret (runtime mode).
         *
         * @param  string $appSecret
         * @return self
         */
        public function setAppSecret($appSecret)
        {
            $this->appSecret = $appSecret;

            return $this;
        }

        /**
         * Set current appId and appSecret.
         *
         * @param    string $id
         * @param    string $secret
         * @return  self
         */
        public function setApp($id, $secret)
        {
            return $this->setAppId($id)->setAppSecret($secret);
        }

        /**
         * Get scope.
         *
         * @param  array $merge
         * @return string|mixed
         */
        public function getScope($merge = array())
        {
            if (count($merge) > 0) {
                return $merge;
            }

            return isAke($this->config, 'scope');
        }

        /**
         * Get Login Url.
         *
         * @param array $scope
         * @param null $version
         * @return string
         */
        public function getLoginUrl($scope = array(), $version = null)
        {
            $scope = $this->getScope($scope);

            return $this->getFacebookHelper()->getLoginUrl($scope, $version);
        }

        /**
         * Redirect to the facebook login url.
         *
         * @param array $scope
         * @param null $version
         * @return Response
         */
        public function authenticate($scope = array(), $version = null)
        {
            return fgc($this->getLoginUrl($scope, $version));
        }

        /**
         * Get the facebook session (access token) when redirected back.
         *
         * @return mixed
         */
        public function getSessionFromRedirect()
        {
            $session = $this->getFacebookHelper()->getSessionFromRedirect();

            $this->session->put('session', $session);

            return $session;
        }

        /**
         * Get token when redirected back from facebook.
         *
         * @return string
         */
        public function getTokenFromRedirect()
        {
            $session = $this->getSessionFromRedirect();

            return $session ? $session->getToken() : null;
        }

        /**
         * Determine whether the "facebook.access_token".
         *
         * @return boolean
         */
        public function hasSessionToken()
        {
            return $this->session->has('access_token');
        }

        /**
         * Get the facebook access token via Session laravel.
         *
         * @return string
         */
        public function getSessionToken()
        {
            return $this->session->get('access_token');
        }

        /**
         * Put the access token to the laravel session manager.
         *
         * @param  string $token
         * @return void
         */
        public function putSessionToken($token)
        {
            $this->session->put('access_token', $token);
        }

        /**
         * Get the access token. If the current access token from session manager exists,
         * then we will use them, otherwise we get from redirected facebook login.
         *
         * @return mixed
         */
        public function getAccessToken()
        {
            if ($this->hasSessionToken())
            {
                return $this->getSessionToken();
            }

            return $this->getTokenFromRedirect();
        }

        /**
         * Get callback from facebook.
         *
         * @return boolean
         */
        public function getCallback()
        {
            $token = $this->getAccessToken();
            if ( ! empty($token))
            {
                $this->putSessionToken($token);

                return true;
            }
            return false;
        }

        /**
         * Get facebook session from laravel session manager.
         *
         * @return string|mixed
         */
        public function getFacebookSession()
        {
            return $this->session->get('session');
        }

        /**
         * Destroy all facebook session.
         *
         * @return void
         */
        public function destroy()
        {
            $this->session->forget('session');
            $this->session->forget('access_token');
        }

        /**
         * Logout the current user.
         *
         * @return void
         */
        public function logout()
        {
            $this->destroy();
        }

        /**
         * Facebook API Call.
         *
         * @param  string $method The request method.
         * @param  string $path The end points path.
         * @param  mixed $parameters Parameters.
         * @param  string $version The specified version of Api.
         * @param  mixed $etag
         * @return mixed
         */
        public function api($method, $path, $parameters = null, $version = null, $etag = null)
        {
            $session = new FacebookSession($this->getAccessToken());

            $request = with(new FacebookRequest($session, $method, $path, $parameters, $version, $etag))
                ->execute()
                ->getGraphObject(GraphUser::className());

            return $request;
        }

        /**
         * Facebook API Request with "GET" method.
         *
         * @param  string $path
         * @param  string|null|mixed $parameters
         * @param  string|null|mixed $version
         * @param  string|null|mixed $etag
         * @return mixed
         */
        public function get($path, $parameters = null, $version = null, $etag = null)
        {
            return $this->api('GET', $path, $parameters, $version, $etag);
        }

        /**
         * Facebook API Request with "POST" method.
         *
         * @param  string $path
         * @param  string|null|mixed $parameters
         * @param  string|null|mixed $version
         * @param  string|null|mixed $etag
         * @return mixed
         */
        public function post($path, $parameters = null, $version = null, $etag = null)
        {
            return $this->api('POST', $path, $parameters, $version, $etag);
        }

        /**
         * Facebook API Request with "DELETE" method.
         *
         * @param  string $path
         * @param  string|null|mixed $parameters
         * @param  string|null|mixed $version
         * @param  string|null|mixed $etag
         * @return mixed
         */
        public function delete($path, $parameters = null, $version = null, $etag = null)
        {
            return $this->api('DELETE', $path, $parameters, $version, $etag);
        }

        /**
         * Facebook API Request with "PUT" method.
         *
         * @param  string $path
         * @param  string|null|mixed $parameters
         * @param  string|null|mixed $version
         * @param  string|null|mixed $etag
         * @return mixed
         */
        public function put($path, $parameters = null, $version = null, $etag = null)
        {
            return $this->api('PUT', $path, $parameters, $version, $etag);
        }

        /**
         * Facebook API Request with "PATCH" method.
         *
         * @param  string $path
         * @param  string|null|mixed $parameters
         * @param  string|null|mixed $version
         * @param  string|null|mixed $etag
         * @return mixed
         */
        public function patch($path, $parameters = null, $version = null, $etag = null)
        {
            return $this->api('PATCH', $path, $parameters, $version, $etag);
        }

        /**
         * Get user profile.
         *
         * @param  array $parameters
         * @param  null  $version
         * @param  null  $etag
         * @return mixed
         */
        public function getProfile($parameters = [], $version = null, $etag = null)
        {
            return $this->get('/me', $parameters, $version, $etag);
        }
    }
