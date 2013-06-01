<?php
    /**
     * Captcha class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Captcha
    {
        private $session;

        public function __construct()
        {
            $this->session = Session::instance('ThinSession');
        }

        public function get()
        {
            $first  = $this->code('ThinCaptcha1');
            $second = $this->code('ThinCaptcha2');
            $html   = '<img src="'. URLSITE .'captcha.php?key=' . $first . '" /> + <img src="'. URLSITE .'captcha.php?key=' . $second . '" />';
            return $html;
        }

        private function code($name)
        {
            $number                 = rand(1, 15);
            $this->session->$name   = $number;
            $height                 = 25;
            $width                  = 60;
            $fontSize               = 14;
            $im                     = imagecreate($width, $height);
            $bg                     = imagecolorallocate($im, 245, 245, 245);
            $textcolor              = imagecolorallocate($im, 0, 0, 0);
            imagestring($im, $fontSize, 5, 5, $number, $textcolor);
            ob_start();
            imagejpeg($im, null, 80);
            $image                  = ob_get_clean();
            $key                    = sha1(time() . session_id() . $number);
            $pathImage              = CACHE_PATH . DS . $key . '.jpg';
            File::put($pathImage, $image);
            imagedestroy($im);
            return $key;
        }
    }
