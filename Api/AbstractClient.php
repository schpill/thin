<?php
    namespace Thin\Api;

    use Thin\Api\Auth\Keyring;
    use Guzzle\Http\Client;
    use Guzzle\Http\Url;
    use Guzzle\Http\Message\RequestInterface;

    class AbstractClient extends Client
    {
        public function __construct()
        {
            parent::__construct(Keyring::getAppUrlRegion());
        }

        /**
         *  Override ti add Zelift auth
         *
         * @param $method
         * @param null $uri
         * @param null $headers
         * @param null $body
         * @return \Guzzle\Http\Message\Request
         */
        public function createRequest($method = RequestInterface::GET, $uri = null, $headers = null, $body = null)
        {

            $request = parent::createRequest($method, $uri, $headers, $body);
            // see http://snippets.webaware.com.au/howto/stop-turning-off-curlopt_ssl_verifypeer-and-fix-your-php-config/
            #$request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);

            // Add Zelift auth headers
            $hTimestamp = $this->getTimestamp();

            $baseSig = Keyring::getAppSecret() . '+' . Keyring::getConsumerKey() . '+' . $method . '+' . $request->getUrl() . '+' . $body . '+' . $hTimestamp;

            $sig = '$1$' . sha1($baseSig);
            $request->addHeader('X-Zelift-Application', Keyring::getAppKey());
            $request->addHeader('X-Zelift-Timestamp', $hTimestamp);
            $request->addHeader('X-Zelift-Consumer', Keyring::getConsumerKey());
            $request->addHeader('X-Zelift-Signature', $sig);

            return $request;
        }

        /**
         * @todo May be usefull to implement for time derive beetween Zelift server termination and us
         *
         *
         * @return int
         */
        protected function getTimestamp()
        {
            return time();
        }
    }
