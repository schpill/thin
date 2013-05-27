<?php
    /**
     * Exception class
     *
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Exception extends \Exception
    {
        /**
         * @var null|Exception
         */
        private $_previous = null;

        /**
         * Construct the exception
         *
         * @param  string $msg
         * @param  int $code
         * @param  Exception $previous
         * @return void
         */
        public function __construct($msg = '', $code = 0, Exception $previous = null)
        {
            if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                parent::__construct($msg, (int) $code);
                $this->_previous = $previous;
            } else {
                parent::__construct($msg, (int) $code, $previous);
            }
        }

        /**
         * Check PCRE PREG error and throw exception
         *
         * @throws Exception
         */
        public static function processPcreError()
        {
            if (preg_last_error() != PREG_NO_ERROR) {
                switch (preg_last_error()) {
                    case PREG_INTERNAL_ERROR:
                        throw new Exception('PCRE PREG internal error');
                    case PREG_BACKTRACK_LIMIT_ERROR:
                        throw new Exception('PCRE PREG Backtrack limit error');
                    case PREG_RECURSION_LIMIT_ERROR:
                        throw new Exception('PCRE PREG Recursion limit error');
                    case PREG_BAD_UTF8_ERROR:
                        throw new Exception('PCRE PREG Bad UTF-8 error');
                    case PREG_BAD_UTF8_OFFSET_ERROR:
                        throw new Exception('PCRE PREG Bad UTF-8 offset error');
                }
            }
        }

        /**
         * Overloading
         *
         * For PHP < 5.3.0, provides access to the getPrevious() method.
         *
         * @param  string $method
         * @param  array $args
         * @return mixed
         */
        public function __call($method, array $args)
        {
            if ('getprevious' == i::lower($method)) {
                return $this->_getPrevious();
            }
            return null;
        }

        /**
         * String representation of the exception
         *
         * @return string
         */
        public function __toString()
        {
            if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                if (null !== ($e = $this->getPrevious())) {
                    return $e->__toString()
                           . "\n\nNext "
                           . parent::__toString();
                }
            }
            return parent::__toString();
        }

        /**
         * Returns previous Exception
         *
         * @return Exception|null
         */
        protected function _getPrevious()
        {
            return $this->_previous;
        }

        public function registerErrorHandler()
        {
            set_error_handler(array($this,'errorHandler'));
        }

        public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
        {
            if (error_reporting() == 0) {
                return;
            }
            if (error_reporting() & $errno) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
        }
    }
