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
            $ch = curl_init('http://api.zendgroup.com/makepdf.php');
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "html=" . urlencode($html));
            $url = curl_exec($ch);
            curl_close($ch);

            $orientation = (true === $portrait) ? 'Portrait' : 'Landscape';

            $purl = 'http://195.154.233.154/api/pdf.php?url=' . urlencode($url) . '&orientation=' . $orientation;

            $pdf = fgc($purl);

            header("Content-type: application/pdf");
            header("Content-Disposition: attachment; filename=\"$name.pdf\"");
            header("Pragma: no-cache");
            header("Expires: 0");
            die($pdf);
        }

        public static function urlToPdf($url, $name = 'document', $portrait = true)
        {
            $html = fgc($url);
            return static::make($html, $name, $portrait);
        }
    }

