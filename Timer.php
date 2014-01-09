<?php
    /**
     * Timer class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Timer
    {
        // command constants
        const CMD_START = 'start';
        const CMD_STOP = 'end';

        // return format constants
        const SECONDS = 0;
        const MILLISECONDS = 1;
        const MICROSECONDS = 2;

        // number of microseconds in a second
        const USECDIV = 1000000;


        /**
         * Stores current state of the timer
         *
         * @var boolean
         */
        private static $_running = false;

        /**
         * Contains the queue of times
         *
         * @var array
         */
        private static $_queue = array();


        /**
         * Start the timer
         *
         * @return void
         */
        public static function start()
        {
            // push current time
            static::_pushTime(static::CMD_START);
        }


        /**
         * Stop the timer
         *
         * @return void
         */
        public static function stop()
        {
            // push current time
            static::_pushTime(static::CMD_STOP);
        }


        /**
         * Reset contents of the queue
         *
         * @return void
         */
        public static function reset()
        {
            // reset the queue
            static::$_queue = array();
        }


        /**
         * Add a time entry to the queue
         *
         * @param string $cmd Command to push
         * @return void
         */
        private static function _pushTime($cmd)
        {
            // capture the time as early in the function as possible
            $mt = microtime();

            // set current running state depending on the command
            if ($cmd == static::CMD_START) {
                // check if the timer has already been started
                if (static::$_running === true) {
                    ThinLog('Timer has already been started');
                    return;
                }

                // set current state
                static::$_running = true;

            } else if ($cmd == static::CMD_STOP) {
                // check if the timer is already stopped
                if (static::$_running === false) {
                    ThinLog('Timer has already been stopped/paused or has not yet been started');
                    return;
                }

                // set current state
                static::$_running = false;

            } else {
                // fail execution of the script
                ThinLog('Invalid command specified');
                return;
            }

            // recapture the time as close to the end of the function as possible
            if ($cmd === static::CMD_START) {
                $mt = microtime();
            }

            // split the time into components
            list($usec, $sec) = explode(' ', $mt);

            // typecast them to the required types
            $sec = (int) $sec;
            $usec = (float) $usec;
            $usec = (int) ($usec * static::USECDIV);

            // create the array
            $time = array(
                $cmd => array(
                    'sec'   => $sec,
                    'usec'  => $usec,
                ),
            );

            // add a time entry depending on the command
            if ($cmd == static::CMD_START) {
                array_push(static::$_queue, $time);

            } else if ($cmd == static::CMD_STOP) {
                $count = count(static::$_queue);
                $array =& static::$_queue[$count - 1];
                $array = array_merge($array, $time);
            }
        }


        /**
         * Get time of execution from all queue entries
         *
         * @param int $format Format of the returned data
         * @return int|float
         */
        public static function get($format = self::SECONDS)
        {
            // stop timer if it is still running
            if (static::$_running === true) {
                ThinLog('Forcing timer to stop', E_USER_NOTICE);
                static::stop();
            }

            // reset all values
            $sec = 0;
            $usec = 0;

            // loop through each time entry
            foreach (static::$_queue as $time) {
                // start and end times
                $start = $time[static::CMD_START];
                $end = $time[static::CMD_STOP];

                // calculate difference between start and end seconds
                $sec_diff = $end['sec'] - $start['sec'];

                // if starting and finishing seconds are the same
                if ($sec_diff === 0) {
                    // only add the microseconds difference
                    $usec += ($end['usec'] - $start['usec']);

                } else {
                    // add the difference in seconds (compensate for microseconds)
                    $sec += $sec_diff - 1;

                    // add the difference time between start and end microseconds
                    $usec += (static::USECDIV - $start['usec']) + $end['usec'];
                }
            }

            if ($usec > static::USECDIV) {
                // move the full second microseconds to the seconds' part
                $sec += (int) floor($usec / static::USECDIV);

                // keep only the microseconds that are over the static::USECDIV
                $usec = $usec % static::USECDIV;
            }

            switch ($format) {
                case static::MICROSECONDS:
                    return ($sec * static::USECDIV) + $usec;

                case static::MILLISECONDS:
                    return ($sec * 1000) + (int) round($usec / 1000, 0);

                case static::SECONDS:
                default:
                    return (float) $sec + (float) ($usec / static::USECDIV);
            }
        }


        /**
         * Get the average time of execution from all queue entries
         *
         * @param int $format Format of the returned data
         * @return float
         */
        public static function getAverage($format = self::SECONDS)
        {
            $count = count(static::$_queue);
            $sec = 0;
            $usec = static::get(static::MICROSECONDS);

            if ($usec > static::USECDIV) {
                // move the full second microseconds to the seconds' part
                $sec += (int) floor($usec / static::USECDIV);

                // keep only the microseconds that are over the static::USECDIV
                $usec = $usec % static::USECDIV;
            }

            switch ($format) {
                case static::MICROSECONDS:
                    $value = ($sec * static::USECDIV) + $usec;
                    return round($value / $count, 2);

                case static::MILLISECONDS:
                    $value = ($sec * 1000) + (int) round($usec / 1000, 0);
                    return round($value / $count, 2);

                case static::SECONDS:
                default:
                    $value = (float) $sec + (float) ($usec / static::USECDIV);
                    return round($value / $count, 2);
            }
        }

        public static function now()
        {
            return new \DateTime('now');
        }

        public static function getMS()
        {
            $mt = microtime();
            list($usec, $sec) = explode(' ', $mt);
            $mt = repl('.', '', ($sec  + $usec));
            if (13 == strlen($mt)) {
                $mt .= '0';
            } elseif (12 == strlen($mt)) {
                $mt .= '00';
            }
            return $mt;
        }

    }
