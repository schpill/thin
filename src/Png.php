<?php
    /**
     * PDF class
     * @author      Gerald Plusquellec
     */
    namespace Thin;

    class Png
    {
        public static function htmlToPng($html, $name = 'image')
        {
            $ch = curl_init('http://api.zendgroup.com/makepdf.php');
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "html=" . urlencode($html));
            $url = curl_exec($ch);
            curl_close($ch);

            $purl = 'http://195.154.233.154/api/png.php?url=' . urlencode($url);

            $pdf = fgc($purl);

            header("Content-type: image/png");
            header("Content-Disposition: attachment; filename=\"$name.png\"");
            header("Pragma: no-cache");
            header("Expires: 0");

            die($pdf);
        }

        public static function urlToPng($url, $name = 'image')
        {
            $purl = 'http://195.154.233.154/api/png.php?url=' . urlencode($url);

            $pdf = fgc($purl);

            header("Content-type: image/png");
            header("Content-Disposition: attachment; filename=\"$name.png\"");
            header("Pragma: no-cache");
            header("Expires: 0");

            die($pdf);
        }
    }

