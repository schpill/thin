<?php
    /**
     * Log class
     * @author      Gerald Plusquellec
     */

    namespace Thin;
    class Log
    {
        static $_logFile;

        public function __construct($logFile = null)
        {
            if (null === $logFile) {
                static::$_logFile = LOGS_PATH . DS . date('Y-m-d') . '.log';
            } else {
                static::$_logFile = $logFile;
            }
            if (!File::exists(static::$_logFile)) {
                File::create(static::$_logFile);
                umask(0000);
                chmod(static::$_logFile, 0777);
            }
        }

        /**
         * Log an exception to the log file.
         *
         * @param  Exception  $e
         * @return void
         */
        public static function exception($e)
        {
            static::write('error', self::exceptionLine($e));
        }

        /**
         * Format a log friendly message from the given exception.
         *
         * @param  Exception  $e
         * @return string
         */
        protected static function exceptionLine($e)
        {
            return $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        }

        /**
         * Write a message to the log file.
         *
         * <code>
         *      // Write an "error" message to the log file
         *      Log::write('error', 'Something went horribly wrong!');
         *
         *      // Write an "error" message using the class' magic method
         *      Log::error('Something went horribly wrong!');
         *
         *      // Log an arrays data
         *      Log::write('info', array('name' => 'Sawny', 'passwd' => '1234', array(1337, 21, 0)), true);
         *      //Result: Array ( [name] => Sawny [passwd] => 1234 [0] => Array ( [0] => 1337 [1] => 21 [2] => 0 ) )
         *      //If we had omit the third parameter the result had been: Array
         * </code>
         *
         * @param  string  $type
         * @param  string  $message
         * @return void
         */
        public static function write($type, $message, $prettyPrint = false)
        {
            if (!isset(static::$_logFile)) {
                static::$_logFile = LOGS_PATH . DS . date('Y-m-d') . '.log';
            }
            if (!File::exists(static::$_logFile)) {
                File::create(static::$_logFile);
            }
            $message = (false !== $prettyPrint) ? print_r($message, true) : $message;
            $message = static::format($type, $message);
            File::append(static::$_logFile, $message);
        }

        protected static function format($type, $message)
        {
            return date('Y-m-d H:i:s') . ' ' . Inflector::upper($type) . " - {$message}". PHP_EOL;
        }

        /**
         * Dynamically write a log message.
         *
         * <code>
         *      // Write an "error" message to the log file
         *      Log::error('This is an error!');
         *
         *      // Write a "warning" message to the log file
         *      Log::warning('This is a warning!');
         *
         *      // Log an arrays data
         *      Log::info(array('name' => 'Sawny', 'passwd' => '1234', array(1337, 21, 0)), true);
         *      //Result: Array ( [name] => Sawny [passwd] => 1234 [0] => Array ( [0] => 1337 [1] => 21 [2] => 0 ) )
         *      //If we had omit the second parameter the result had been: Array
         * </code>
         */
        public static function __callStatic($method, $parameters)
        {
            $prettyPrint = count($parameters) == 2 ? Arrays::last($parameters) : false;
            static::write($method, Arrays::first($parameters), $prettyPrint);
        }

    }
