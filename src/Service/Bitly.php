<?php
    namespace Thin\Service;
    class Bitly
    {
        const URI_BASE = 'http://api.bit.ly';

        const STATUS_OK = 'OK';
        const STATUS_RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
        const STATUS_INVALID_URI = 'INVALID_URI';
        const STATUS_MISSING_ARG_LOGIN = 'MISSING_ARG_LOGIN';
        const STATUS_UNKNOWN_ERROR = 'UNKNOWN_ERROR';

        const URL_SHORTEN = '/v3/shorten';
        const URL_EXPAND = '/v3/expand';
        const URL_CLICKS = '/v3/clicks';
        const URL_PRO_DOMAIN = '/v3/bitly_pro_domain';
        const URL_LOOKUP = '/v3/lookup';
        const URL_AUTHENTICATE = '/v3/authenticate';
        const URL_INFO = '/v3/info';
        const URL_VALIDATE = '/v3/validate';

        /**
        *
        * @var Zend_Http_Response
        */
        protected $_response = null;

        /**
        *
        * @var array
        */
        protected $_data = null;

        /**
        * @return Zend_Rest_Client
        */
        protected function _getClient()
        {
            if (null === $this->_client) {
                $this->_client = new \Zend_Rest_Client(static::URI_BASE);
            }
            return $this->_client;
        }

        protected function _checkErrors()
        {
            switch ($this->_data['status_txt']) {
                case static::STATUS_OK:
                    break;
                case static::STATUS_RATE_LIMIT_EXCEEDED:
                case static::STATUS_INVALID_URI:
                case static::STATUS_MISSING_ARG_LOGIN:
                case static::STATUS_UNKNOWN_ERROR:
                default:
                    throw new \Thin\Exception('Error in Bit.ly service : ' . $this->_data['status_txt'], $this->_data['status_code']);
                    break;
            }
        }

        /* needed ?? */
        public function __construct(array $config = array())
        {

        }

        /**
        *
        * @param string $path
        * @param array $options
        */
        protected function _callApi($path, $options)
        {

            $param['format'] = 'json';

            $this->_response = $restClient->restGet($path, $options);

            switch ($param['format']) {
                case 'json':
                    $this->_data = json_decode($this->_response->getBody());
                    break;
                case 'xml':
                    throw new \Thin\Exception('Not yet implemented. Please use json format.');
                    break;
            }

            $this->_checkErrors();

            return $this->_data['data'];
        }

        public function shorten($param)
        {
            if (is_string($param)) {
                $param = array('longUrl' => $param);
            }

            if (!isset($param['longUrl'])) {
                throw new \Thin\Exception('longUrl is need to shorten it.');
            }

            $url = static::URL_SHORTEN;

            $result = $this->_callApi($url, $param);

            return $result['url'];
        }

        public function expand($shortUrl)
        {
            throw new \Thin\Exception('Not yet implemented');
        }

        public function clicks()
        {
            throw new \Thin\Exception('Not yet implemented');
        }

        public function proDomain()
        {
            throw new \Thin\Exception('Not yet implemented');
        }

        public function loookup()
        {
            throw new \Thin\Exception('Not yet implemented');
        }

        public function authenticate()
        {
            throw new \Thin\Exception('Not yet implemented');
        }

        public function info()
        {
            throw new \Thin\Exception('Not yet implemented');
        }

        public function validate()
        {
            throw new \Thin\Exception('Not yet implemented');
        }
    }
