 <?php
    namespace Thin;
    class Googlesearch
    {
        private $source = '';
        private $links = array();
        private $pages;
        private $q;

        public function __construct(){}

        public function fetch()
        {
            return $this->links;
        }

        public function search($q)
        {
            $this->q = $q;
            $this->getPagination();
            $i = 1;
            do {
                $this->getSource((($i > 1) ? false : true), $i);
                $i++;
                $this->getLinks();
            } while ($i <= $this->pages);
        }

        private function writeLinks()
        {
            if(count($this->links) > 0) {
                $str = implode("\n", $this->links);
                $filename = 'links/Links' . (int) $this->q . '' . mt_rand(0, 255) . mt_rand(0, 255) . '.txt';
                $h = fopen($filename, 'a+');
                fwrite($h, $str);
                fclose($h);
            }
        }

        public function getSource($first = true, $page = 1)
        {
            $string = urlencode($this->q);
            $link = 'http://www.google.com/search?q=';
            if($first) {
                $link .= $string . '&hl=pt-BR&newwindow=1&prmd=imvns&filter=0&biw=1366&bih=667';
            } else {
                $link .= $string;
                $link .= '&hl=pt-BR&newwindow=1&prmd=imvns&ei=9NtQULzRD-r00gGpnYD4CQ&start=';
                $link .= (($page - 1) * 10);
                $link .= '&sa=N&filter=0&biw=1366&bih=667';
            }
            $this->source = dwn($link);
        }

        private function getPagination()
        {
            $this->getSource(true);
            preg_match_all('/<\s*td[^>]*>(.*?)<\/td>/', $this->source, $matches, PREG_SET_ORDER);
            $this->pages = 1;
            $pagination = array();
            if(count($matches) > 0) {
                foreach($matches as $match) {
                    if(preg_match('/<a href=/i', substr($match[1], 0, 15))) {
                        $this->pages++;
                    }
                }
            }
        }

        private function sanitize($_)
        {
            if(!is_string($_)) {
                return false;
            }
            if(empty($_)) {
                return false;
            }
            $stop = strlen($_);
            $_ = str_replace('<a href="/url?q=', '', Inflector::lower($_));
            for($i = 0; $i < $stop; $i++) {
                if(Arrays::inArray($_[$i], array('&amp;', '"', '&', "'"))) {
                    $stop = $i;
                    $i = strlen($_);
                }
            }
            $url = '';
            for($j = 0;$j < $stop; $j++) {
                $url .= $_[$j];
            }
            return rawurldecode($url);
        }

        private function getLinks()
        {
            $regex = '/<\s*h3[^>]*>(.*?)<\/h3>/';
            preg_match_all($regex, $this->source, $matches, PREG_SET_ORDER);
            if(count($matches) > 0) {
                foreach($matches as $match) {
                    if(preg_match('/string/i', $match[1])) {
                        $this->links[] = $yhis->sanitize($match[1]);
                    }
                }
            }
        }
    }
