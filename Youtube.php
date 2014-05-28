<?php
    namespace Thin;
    class Youtube
    {
        const USER_UPLOADS_URI          = 'https://gdata.youtube.com/feeds/api/users/##userId##/uploads';
        const VIDEO_INFOS_URI           = 'https://gdata.youtube.com/feeds/api/videos/##videoId##';

        public static function getVideosByUser($userId)
        {
            set_time_limit(0);
            $videos                     = array();
            $firstPage                  = dwn(repl('##userId##', $userId, static::USER_UPLOADS_URI));
            $next                       = false;
            if (strstr($firstPage, "<link rel='next' type='application/atom+xml' href=")) {
                $nextUrl                = urldecode(Utils::cut("<link rel='next' type='application/atom+xml' href='", "'", $firstPage));
                $next                   = true;
            }
            $items                      = explode('<entry>', $firstPage);
            array_shift($items);
            $nbItems                    = count($items);
            foreach ($items as $item) {
                list($item, $dummy)     = explode('</entry>', trim($item), 2);
                $id                     = Utils::cut('<id>http://gdata.youtube.com/feeds/api/videos/', '<', $item);
                $title                  = Utils::cut('<title type=\'text\'>', '</title>', $item);
                $videos[]               = array('youtube_id' => $id, 'title' => $title, 'user' => $userId);
            }
            while (true === $next) {
                $page                   = dwn($nextUrl);
                $next                   = strstr($page, "<link rel='next'") ? true : false;
                $items                  = explode('<entry>', $page);
                array_shift($items);
                $nbItems                = count($items);
                foreach ($items as $item) {
                    list($item, $dummy) = explode('</entry>', trim($item), 2);
                    $id                 = Utils::cut('<id>http://gdata.youtube.com/feeds/api/videos/', '<', $item);
                    $title              = Utils::cut('<title type=\'text\'>', '</title>', $item);
                    $videos[]           = array('youtube_id' => $id, 'title' => $title, 'user' => $userId);
                }
                if (true === $next) {
                    $nextUrl            = urldecode(Utils::cut("<link rel='next' type='application/atom+xml' href='", "'", $page));
                }
            }
            return $videos;
        }

        public static function getInfosVideo($videoId, $returnObject = true)
        {
            $xml    = dwn(repl('##videoId##', $videoId, static::VIDEO_INFOS_URI));
            $xml    = simplexml_load_string($xml);
            $json   = json_encode($xml);
            $array  = json_decode($json, true);
            if (true === $returnObject) {
                $obj = new Container($array);
                return $obj;
            }
            return $array;
        }
    }
