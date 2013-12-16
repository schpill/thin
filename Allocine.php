<?php
    namespace Thin;
    class Allocine
    {
        public static function getInfosMovie($id)
        {
            $html    = dwn('http://vod.canalplay.com/films/cinema/holiday,297,308,' . $id . '.aspx');
            if (strstr($html, 'Object moved to')) {
                $url     = 'http://vod.canalplay.com' . urldecode(Utils::cut('<h2>Object moved to <a href="', '"', $html));
                $html    = dwn($url);
            }

            $story   = Utils::cut('alt="L\'histoire" title="L\'histoire" /></h2><p>', '</p>', $html);
            $title   = Utils::cut('var title="', '"', $html);
            $image   = 'http://canalplay-publishing.canal-plus.com/movies/' . $id . '/pictures/' . $id . '_pivot.jpg';
            $purpose = Utils::cut('title="A propos du film" /></h2><p>', '</p></div></div>', $html);

            $distribution   = array();
            $tab            = explode("title=\"Voir tous les films de/avec '", $html);

            if (count($tab)) {
                for ($i = 1 ; $i < count($tab) - 1 ; $i++) {
                    $seg = trim($tab[$i]);
                    if (strstr($seg, "</a>") && strstr($seg, "underline")) {
                        $person = Utils::cut('\'">', '</a>', $seg);
                        if (!Arrays::inArray($person, $distribution)) {
                            $distribution[] = $person;
                        }
                    }
                }
            }

            $director   = null;
            $actors     = array();

            if (count($distribution)) {
                $director = current($distribution);
                $actors = array();
                if (1 < count($distribution)) {
                    for ($j = 1 ; $j < count($distribution) ; $j++) {
                        $actors[] = $distribution[$j];
                    }
                }
            }

            $other                  = Utils::cut('<li style="clear:left;"><strong><br />', '</li>', $html);
            list($first, $second)   = explode('</strong>- ', $other, 2);
            list($country, $year)   = explode("-", $first, 2);
            $country = substr($country, 0, -1);
            $year = repl("\n", '', $year);
            $year = repl("\r", '', $year);
            $year = repl("\n", '', $year);
            list($duration, $dummy) = @explode("\n", $second, 2);

            $infos = array(
                'title'     => $title,
                'story'     => $story,
                'image'     => $image,
                'purpose'   => $purpose,
                'director'  => $director,
                'actors'    => $actors,
                'country'   => $country,
                'year'      => $year,
                'duration'  => $duration,
            );
            return $infos;
        }

        public static function save($id)
        {
            $res = Data::query('movie', 'id_ac = ' . $id);
            if (!count($res)) {
                $info               = static::getInfosMovie($id);
                $info['id_ac']      = $id;
                $info['id_video']   = null;
                $info['plateforme'] = null;
                $movie              = Data::getById('movie', Data::add('movie', $info));
            } else {
                $movie = Data::getObject(current($res));
            }

            return $movie;
        }

    }
