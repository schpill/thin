<?php
    /**
     * PDF class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Pdf
    {
        public static function make($html, $name = 'document', $portrait = true)
        {
            $orientation = (true == $portrait) ? 'Portrait' : 'Landscape';
            $data = "orientation=$orientation&html=" . urlencode($html);

            $ch = curl_init('http://fr.webz0ne.com/pdf/');
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $pdf = curl_exec($ch);
            curl_close($ch);
            $redirect = URLSITE . 'file.php?type=pdf&name=' . $name . '&file=' . md5($pdf);
            $cache = CACHE_PATH . DS . md5($pdf) . '.pdf';
            file_put_contents($cache, $pdf);
            Utils::go($redirect);
        }

        public static function urlToPdf($url, $name = 'document', $portrait = true)
        {
            $html = fgc($url);
            return static::make($html, $name, $portrait);
        }
    }
