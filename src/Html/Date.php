<?php
    namespace Thin\Html;
    class Date
    {
        public static function format($date, $formatIn, $formatOut)
        {
            $date = \DateTime::createFromFormat($formatIn, $date);
            return $date->format($formatOut);
        }

        public static function timestamp($date)
        {
            if (is_numeric($date)) {
                return $date;
            } elseif (preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{4}$/', $date)) {
                list($d, $m, $y) = explode('/', $date, 3);
                return mktime(0, 0, 0, $m, $d, $y);
            } elseif (preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/', $date)) {
                list($y, $m, $d) = explode('-', $date, 3);
                return mktime(0, 0, 0, $m, $d, $y);
            } elseif (preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{4} [0-9]{2}\:[0-9]{2}$/', $date)) {
                $date = preg_replace('/[^0-9\/]/', '/', $date);
                list($d, $m, $y, $h, $i) = explode('/', $date, 5);
                return mktime($h, $i, 0, $m, $d, $y);
            } elseif (preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{2} [0-9]{2}\:[0-9]{2}\:[0-9]{2},[0-9]+$/', $date)) {
                $date = current(explode(',', $date));
                $date = preg_replace('/[^0-9\/]/', '/', $date);
                list($d, $m, $y, $h, $i) = explode('/', $date, 5);
                return mktime($h, $i, 0, $m, $d, $y);
            } else {
                list($dateYMD, $hourHIS) = explode(' ', $date, 2);
                list($year, $month, $day) = explode('-', $dateYMD, 3);
                list($hour, $minute, $sec) = explode(':', $hourHIS, 3);
                return mktime($hour, $minute, $sec, $month, $day, $year);
            }
        }

        public static function date($date, $format = 'd/m/y')
        {
            if (!empty($date) && is_numeric($date)) {
                return date($format, $date);
            } else {
                return null;
            }
        }

        public static function getDateFromDayAndHour($day, $hourInSec)
        {
            $hour   = floor($hourInSec / 3600);
            $minute = floor(($hourInSec - ($hour * 3600)) / 60);
            $second = $hourInSec - ($hour * 3600) - ($minute * 60);
            return mktime($hour, $minute, $second, date('n', $day), date('j', $day), date('Y', $day));
        }

    /**
     * Parses a RFC2616-compatible date string
     *
     * This method returns false if the date is invalid
     *
     * @param string $dateHeader
     * @return bool|DateTime
     */
    public static function parseHttpDate($dateHeader)
    {
            //RFC 2616 section 3.3.1 Full Date
            //Only the format is checked, valid ranges are checked by strtotime below
            $month      = '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)';
            $weekday    = '(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)';
            $wkday      = '(Mon|Tue|Wed|Thu|Fri|Sat|Sun)';
            $time       = '[0-2]\d(\:[0-5]\d){2}';
            $date3      = $month . ' ([1-3]\d| \d)';
            $date2      = '[0-3]\d\-' . $month . '\-\d\d';
            //4-digit year cannot begin with 0 - unix timestamp begins in 1970
            $date1      = '[0-3]\d ' . $month . ' [1-9]\d{3}';

            //ANSI C's asctime() format
            //4-digit year cannot begin with 0 - unix timestamp begins in 1970
            $asctimeDate    = $wkday . ' ' . $date3 . ' ' . $time . ' [1-9]\d{3}';
            //RFC 850, obsoleted by RFC 1036
            $rfc850Date     = $weekday . ', ' . $date2 . ' ' . $time . ' GMT';
            //RFC 822, updated by RFC 1123
            $rfc1123Date    = $wkday . ', ' . $date1 . ' ' . $time . ' GMT';
            //allowed date formats by RFC 2616
            $httpDate       = "($rfc1123Date|$rfc850Date|$asctimeDate)";

            //allow for space around the string and strip it
            $dateHeader = trim($dateHeader, ' ');
            if (!preg_match('/^' . $httpDate . '$/', $dateHeader))
                return false;

            //append implicit GMT timezone to ANSI C time format
            if (strpos($dateHeader, ' GMT') === false)
                $dateHeader .= ' GMT';


            $realDate = strtotime($dateHeader);
            //strtotime can return -1 or false in case of error
            if ($realDate !== false && $realDate >= 0)
                return new \DateTime('@' . $realDate, new DateTimeZone('UTC'));

        }

        /**
         * Transforms a DateTime object to HTTP's most common date format.
         *
         * We're serializing it as the RFC 1123 date, which, for HTTP must be
         * specified as GMT.
         *
         * @param \DateTime $dateTime
         * @return string
         */
        public static function toHttpDate(\DateTime $dateTime)
        {
            // We need to clone it, as we don't want to affect the existing
            // DateTime.
            $dateTime = clone $dateTime;
            $dateTime->setTimeZone(new \DateTimeZone('GMT'));
            return $dateTime->format('D, d M Y H:i:s \G\M\T');
        }

        public static function age($datetime)
        {
            $birthDate  = date("m/d/Y", self::timestamp($datetime));
            $birthDate  = explode("/", $birthDate);

            $age        = (date("md", date("U", mktime(0, 0, 0, $birthDate[0], $birthDate[1], $birthDate[2]))) > date("md") ? ((date("Y") - $birthDate[2]) - 1) : (date("Y") - $birthDate[2]));
            return $age;
        }
    }
