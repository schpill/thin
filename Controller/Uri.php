<?php
    namespace Thin\Controller;

    use Thin\Exception;

    abstract class Uri
    {
        /**
         * Scheme of this URI (http, ftp, etc.)
         *
         * @var string
         */
        protected $_scheme = '';

        /**
         * Global configuration array
         *
         * @var array
         */
        static protected $_config = array(
            'allow_unwise' => false
        );

        /**
         * Return a string representation of this URI.
         *
         * @see    getUri()
         * @return string
         */
        public function __toString()
        {
            try {
                return $this->getUri();
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                return '';
            }
        }

        /**
         * Convenience function, checks that a $uri string is well-formed
         * by validating it but not returning an object.  Returns TRUE if
         * $uri is a well-formed URI, or FALSE otherwise.
         *
         * @param  string $uri The URI to check
         * @return boolean
         */
        public static function check($uri)
        {
            try {
                $uri = self::factory($uri);
            } catch (Exception $e) {
                return false;
            }

            return $uri->valid();
        }

        /**
         * Create a new Uri object for a URI.  If building a new URI, then $uri should contain
         * only the scheme (http, ftp, etc).  Otherwise, supply $uri with the complete URI.
         *
         * @param  string $uri       The URI form which a Uri instance is created
         * @param  string $className The name of the class to use in order to manipulate URI
         * @throws Exception When an empty string was supplied for the scheme
         * @throws Exception When an illegal scheme is supplied
         * @throws Exception When the scheme is not supported
         * @throws Exception When $className doesn't exist or doesn't implements Uri
         * @return Uri
         * @link   http://www.faqs.org/rfcs/rfc2396.html
         */
        public static function factory($uri = 'http', $className = null)
        {
            // Separate the scheme from the scheme-specific parts
            $uri            = explode(':', $uri, 2);
            $scheme         = strtolower($uri[0]);
            $schemeSpecific = isset($uri[1]) === true ? $uri[1] : '';

            if (strlen($scheme) === 0) {
                throw new Exception('An empty string was supplied for the scheme');
            }

            // Security check: $scheme is used to load a class file, so only alphanumerics are allowed.
            if (ctype_alnum($scheme) === false) {
                throw new Exception('Illegal scheme supplied, only alphanumeric characters are permitted');
            }

            if ($className === null) {
                /**
                 * Create a new Uri object for the $uri. If a subclass of Uri exists for the
                 * scheme, return an instance of that class. Otherwise, a Exception is thrown.
                 */
                switch ($scheme) {
                    case 'http':
                        // Break intentionally omitted
                    case 'https':
                        $className = '\\Thin\\Controller\\Uri\\Http';
                        break;

                    case 'mailto':
                        // TODO
                    default:
                        throw new Exception("Scheme $scheme is not supported");
                        break;
                }
            }

            $schemeHandler = new $className($scheme, $schemeSpecific);

            if (! $schemeHandler instanceof Uri) {
                throw new Exception('"' . $className . '" is not an instance of Uri');
            }

            return $schemeHandler;
        }

        /**
         * Get the URI's scheme
         *
         * @return string|false Scheme or false if no scheme is set.
         */
        public function getScheme()
        {
            if (empty($this->_scheme) === false) {
                return $this->_scheme;
            } else {
                return false;
            }
        }

        /**
         * Set global configuration options
         *
         * @param array $config
         */
        static public function setConfig($config)
        {
            if (!is_array($config)) {
                throw new Exception("Config must be an array.");
            }

            foreach ($config as $k => $v) {
                self::$_config[$k] = $v;
            }
        }

        /**
         * Uri and its subclasses cannot be instantiated directly.
         * Use Uri::factory() to return a new Uri object.
         *
         * @param string $scheme         The scheme of the URI
         * @param string $schemeSpecific The scheme-specific part of the URI
         */
        abstract protected function __construct($scheme, $schemeSpecific = '');

        /**
         * Return a string representation of this URI.
         *
         * @return string
         */
        abstract public function getUri();

        /**
         * Returns TRUE if this URI is valid, or FALSE otherwise.
         *
         * @return boolean
         */
        abstract public function valid();
    }
