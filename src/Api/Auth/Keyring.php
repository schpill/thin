<?php
    namespace Thin\Api\Auth;

    /**
     * TODO :
     *      - comments
     *      - Sanity Check on setter
     *
     *
     */

    class Keyring
    {
        static private $appKey          = null; // API Application Key
        static private $appSecret       = null; // API Application Secret
        static private $consumerKey     = null; // API Consumer Key
        static private $host            = null; // API host
        static private $protocol            = 'https'; // API protocol
        static private $appUrlRegion    = null; // Choix URL API (FR, CA...)

        public static function setAppKey($key)
        {
            static::$appKey = (string) $key;
        }

        public static function getAppKey()
        {
            return static::$appKey;
        }

        public static function setAppSecret($secret)
        {
            static::$appSecret = (string) $secret;
        }

        public static function getAppSecret()
        {
            return static::$appSecret;
        }

        public static function setConsumerKey($key)
        {
            static::$consumerKey = (string) $key;
        }

        public static function getConsumerKey()
        {
            return static::$consumerKey;
        }

        public static function setAppHost($host)
        {
            static::$host = (string) $host;
        }

        public static function getAppHost()
        {
            return static::$host;
        }

        public static function setAppProtocol($protocol)
        {
            static::$protocol = (string) $protocol;
        }

        public static function getAppProtocol()
        {
            return static::$protocol;
        }

        /** Paramètre "UrlRegion"
        * FR = fr.api.zelift.com
        * CA = ca.api.zelift.com
        */
        public static function setAppUrlRegion($country)
        {
            static::$appUrlRegion = static::getUrlApi($country);
        }

        public static function getAppUrlRegion()
        {
            return static::$appUrlRegion;
        }

        public static function getUrlApi($country)
        {
            if ($country == 'FR') {
                return static::$protocol . 'https://fr.' . static::$host . '/';
            } elseif ($country == 'CA') {
                return static::$protocol . 'https://ca.' . static::$host . '/';
            }
        }
    }
