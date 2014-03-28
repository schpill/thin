<?php
    namespace Thin;
    class Cookies
    {
        const ONE_DAY_AS_SECONDS = 86400;
    	const FOREVER = 2628000;
    	/**
    	 * @param string $name
    	 * @param string $value
    	 * @param string $expired
    	 * @param string $path
    	 * @param string $domain
    	 * @return void
    	 */
        public static function set($name, $value, $expire = self::ONE_DAY_AS_SECONDS, $path = null, $domain = null)
        {
            setCookie($name, $value, $expire, $path, $domain);
        }

        /**
         * @param string $name
         * @return mixed
         */
        public static function get($name, $default = null)
        {
            if (Arrays::exists($name, $_COOKIE)) {
                if (isset($_COOKIE[$name])) {
                    return $_COOKIE[$name];
                }
            }
            return $default;
        }

        public static function getAll()
        {
            $cookies = new ThinCookies;
        	return $cookies->populate($_COOKIE);
        }

        /**
         * @param string $name
         */
        public static function destroy($name)
        {
        	$destructionDateTime = new \DateTime();
        	$destructionDateTime->sub(new \DateInterval("P1D"));
            static::set($name, "", $destructionDateTime->getTimestamp(), '/');
        }
    }
