<?php
    /**
     * Html QR class
     * @author      Gerald Plusquellec
     */
    namespace Thin\Html;
    class Qr
    {
        /* Plain text */

        public static function text($text, $sizeH = 350, $sizeW = 350)
        {
            return 'http://chart.apis.google.com/chart?cht=qr&chs=' . $sizeW . 'x' . $sizeH . '&chl=' . urlencode($text);
        }

        /* E-mail addresses */

        public static function email($email, $sizeH = 350, $sizeW = 350)
        {
            return 'http://chart.apis.google.com/chart?cht=qr&chs=' . $sizeW . 'x' . $sizeH . '&chl=mailto%3A' . urlencode($email);
        }

        /* Phone numbers */

        public static function phoneNumber($number, $sizeH = 350, $sizeW = 350)
        {
            return 'http://chart.apis.google.com/chart?cht=qr&chs=' . $sizeW . 'x' . $sizeH . '&chl=tel%3A' . urlencode($number);
        }

        /* URL */

        public static function url($url, $sizeH = 350, $sizeW = 350)
        {
            return 'http://chart.apis.google.com/chart?cht=qr&chs=' . $sizeW . 'x' . $sizeH . '&chl=' . urlencode($url);
        }

        /* SMS */

        public static function sms($receiver, $message, $sizeH = 350, $sizeW = 350)
        {
            return 'http://chart.apis.google.com/chart?cht=qr&chs=' . $sizeW . 'x' . $sizeH . '&chl=smsto%3A' . urlencode($receiver).'%3A' . urlencode($message);
        }

        /* Wifi network */

        public static function wifi($ssid, $password, $type, $sizeH = 350, $sizeW = 350)
        {
            return 'http://chart.apis.google.com/chart?cht=qr&chs=' . $sizeW . 'x' . $sizeH . '&chl=WIFI%3AS%3A' . $ssid . '%3BT%3A' . $type . '%3BP%3A' . $password . '%3B%3B';
        }

        /* Save image */

        public static function save($image, $destination)
        {
            if(file_exists($destination)) {
                return false;
            } else {
                $img = imagecreatefrompng($image);
                imagepng($img, $destination);
                return true;
            }
        }
    }

