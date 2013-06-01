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
            $data = "html=" . urlencode($html);
            $ch = curl_init('http://api.zendgroup.com/makepdf.php');
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $url = curl_exec($ch);
            curl_close($ch);

            $orientation = (true == $portrait) ? 'Portrait' : 'Landscape';

            $pdfUrl = 'http://fr.webz0ne.com/api/pdf.php?url=' . urlencode($url) . '&orientation=' . $orientation;
            $pdf = fgc($pdfUrl);

            header("Content-type: application/pdf");
            header("Content-Length: " . Inflector::length($pdf));
            header("Content-Disposition: attachement; filename=$name.pdf");
            die($pdf);
        }

        public static function urlToPdf($url, $name = 'document', $portrait = true)
        {
            $html = fgc($url);
            return static::make($html, $name, $portrait);
        }
    }
