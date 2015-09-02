<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2015 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */
    namespace Thin;

    class Phalcon
    {
        public static function pdf($url, $orientation = 'portrait', $dir = '/tmp')
        {
            $pdfs = glob($dir . '/*.pdf');

            foreach ($pdfs as $pdf) {
                @unlink($pdf);
            }

            $pdf = $dir . '/' . sha1($orientation . $url . time()) . '.pdf';

            if ('portrait' == $orientation) {
                $cmd = "xvfb-run -a -s \"-screen 0 640x480x16\" wkhtmltopdf --dpi 600 --page-size A4 $url $pdf";
            } else {
                $cmd = "xvfb-run -a -s \"-screen 0 640x480x16\" wkhtmltopdf --dpi 600 --page-size A4 -O Landscape $url $pdf";
            }

            $output = shell_exec($cmd);

            $cnt = file_get_contents($pdf);

            die($cnt);
        }
    }
