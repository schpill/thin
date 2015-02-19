<?php
    namespace Thin;
    class Music
    {
        public static function play($song)
        {
            $song = static::decode($song);
            $html = '<div>
    <object data="http://www.deezer.com/embedded/small-widget-v2.swf?idSong=' . $song . '&amp;ap=1&amp;ln=fr&amp;sl=0&amp;autoplay=1" type="application/x-shockwave-flash" height="55" width="220">
        <param name="wmode" value="transparent" />
        <param name="src" value="http://www.deezer.com/embedded/small-widget-v2.swf?idSong=' . $song . '&amp;ap=1&amp;ln=fr&amp;sl=0&amp;autoplay=1" />
    </object>
</div>';
            return $html;
        }

        public static function album($id)
        {
            $file = MUSIC_PATH . DS . 'albums/' . $id . '.json';
            if (!file_exists($file)) {
                @touch($file);
                chmod($file, 0777);
                $fp = fopen($file, 'a');
                $json = file_get_contents("http://api.deezer.com/2.0/album/$id?output=json");
                fwrite($fp, $json);
                fclose($fp);
            } else {
                $json = file_get_contents($file);
            }
            return $json;
        }

        public static function song($id)
        {
            $file = MUSIC_PATH . DS . 'songs/' . $id . '.json';
            if (!file_exists($file)) {
                @touch($file);
                chmod($file, 0777);
                $fp = fopen($file, 'a');
                $json = file_get_contents("http://api.deezer.com/2.0/track/$id?output=json");
                fwrite($fp, $json);
                fclose($fp);
            } else {
                $json = file_get_contents($file);
            }
            return $json;
        }

        public static function playlist($id)
        {
            $tab = json_decode(file_get_contents("http://api.deezer.com/2.0/playlist/$id/tracks?output=json"), true);
            $data = $tab['data'];
            if (Arrays::exists('next', $tab)) {
                $next = $tab['next'];
                if (isset($next)) {
                    return static::next($next, $data);
                }
            }
            return $data;
        }

        private static function next($url, $data)
        {

            $tab = json_decode(file_get_contents($url), true);
            $data = array_merge($data, $tab['data']);
            if (Arrays::exists('next', $tab)) {
                $next = $tab['next'];
                if (isset($next)) {
                    return static::next($next, $data);
                }
            }
            return $data;
        }

        public static function search($search)
        {
            $file = MUSIC_PATH . DS . 'search' . DS . md5($search) . '.cache';
            if (!File::exists($file)) {
                $infos = json_decode(file_get_contents('http://api.deezer.com/2.0/search?output=json&q=' . urlencode($search)), 1);
                $total = $infos['total'];
                $searchData = array();
                $collection = array();
                if (0 < $total) {
                    $searchData[] = $infos;
                    if (isset($infos['next'])) {
                        $continue = true;
                        $next = $infos['next'];
                        while (true === $continue) {
                            $infosNext = json_decode(file_get_contents($next), 1);
                            $searchData[] = $infosNext;
                            if (isset($infosNext['next'])) {
                                $next = $infosNext['next'];
                            } else {
                                $continue = false;
                            }
                        }
                    }
                    array_push($collection, static::fetch($searchData));
                }
                file_put_contents($file, json_encode($collection));
            } else {
                $collection = json_decode(fgc($file));
            }
            return $collection;
        }

        public static function fetch(array $array)
        {
            $infos = array();
            foreach ($array as $page) {
                foreach ($page['data'] as $song) {
                    $idSong     = $song['id'];
                    $infoSong   = json_decode(static::song($idSong), 1);
                    $readable   = $infoSong['readable'];
                    $titleSong  = $infoSong['title'];
                    $titleAlbum = $infoSong['album']['title'];
                    $idAlbum    = $infoSong['album']['id'];
                    static::album($idAlbum);
                    if (true === $readable) {
                        $idArtist   = $infoSong['artist']['id'];
                        $nameArtist = $infoSong['artist']['name'];
                        $duration   = $infoSong['duration'];
                        $vignette   = 'http://api.deezer.com/2.0/album/' . $idAlbum . '/image';
                        $infos[$idSong] = array(
                            'TITRE'     => utf8_decode($titleSong),
                            'IDARTIST'  => $idArtist,
                            'ARTISTE'   => utf8_decode($nameArtist),
                            'IDALBUM'   => $idAlbum,
                            'ALBUM'     => utf8_decode($titleAlbum),
                            'COVER'     => $vignette,
                            'DURATION'  => $duration
                        );
                    }
                }
            }
            return $infos;
        }

        public static function decode($str)
        {
            $str = repl('a', '0', $str);
            $str = repl('i', '1', $str);
            $str = repl('r', '2', $str);
            $str = repl('h', '3', $str);
            $str = repl('z', '4', $str);
            $str = repl('c', '5', $str);
            $str = repl('v', '6', $str);
            $str = repl('yt', '7', $str);
            $str = repl('km', '8', $str);
            $str = repl('sg', '9', $str);
            return $str;
        }

        public static function encode($str)
        {
            $str = repl('0', 'a', $str);
            $str = repl('1', 'i', $str);
            $str = repl('2', 'r', $str);
            $str = repl('3', 'h', $str);
            $str = repl('4', 'z', $str);
            $str = repl('5', 'c', $str);
            $str = repl('6', 'v', $str);
            $str = repl('7', 'yt', $str);
            $str = repl('8', 'km', $str);
            $str = repl('9', 'sg', $str);
            return $str;
        }

        public static function duration($duration)
        {
            if (3600 > $duration) {
                return sprintf("%02d:%02d", ($duration / 60), $duration % 60);
            } else {
                $hour = sprintf("%02d", ($duration / 3600));
                $minutes = $duration - ($hour * 3600);
                return $hour . ':' . sprintf("%02d:%02d", ($minutes / 60), $minutes % 60);
            }
        }
    }
