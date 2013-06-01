<?php
    /**
     * RSS class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Rss
    {
        public $title;
        public $link;
        public $description;
        public $language = 'fr-FR';
        public $pubDate;
        public $url;
        public $items;
        public $tags;
        public $useCDataTags;

        public function __construct()
        {
            $this->items = array();
            $this->tags  = array();
            $this->useCDataTags = true;
            $this->setPubDate();
            $this->url = $this->fullUrl();
        }

        public function addItem($item)
        {
            $this->items[] = $item;
        }

        public function setPubDate($date = null)
        {
            if(is_null($date)) {
                $date = time();
            }
            if(!ctype_digit($date)) {
                $date = strtotime($date);
            }
            $this->pubDate = date('D, d M Y H:i:s O', $date);
        }

        public function addTag($tag, $value)
        {
            $this->tags[$tag] = $value;
        }

        public function loadRecordset(array $data, $title, $link, $description, $pubDate)
        {
            if (count($data)) {
                foreach ($data as $row) {
                    $item = new Rss\Item();
                    $item->title       = $row[$title];
                    $item->link        = $row[$link];
                    $item->description = $row[$description];
                    $item->setPubDate($row[$pubDate]);
                    $this->addItem($item);
                }
            }
        }

        public function out()
        {
            $bad         = array('&', '<');
            $good        = array('&#x26;', '&#x3c;');
            $title       = repl($bad, $good, $this->title);
            $description = repl($bad, $good, $this->description);

            $out  = $this->header();
            $out .= "<channel>\n";
            $out .= "<title>" . $title . "</title>\n";
            $out .= "<link>" . $this->link . "</link>\n";
            $out .= "<description>" . $description . "</description>\n";
            $out .= "<language>" . $this->language . "</language>\n";
            $out .= "<pubDate>" . $this->pubDate . "</pubDate>\n";
            $out .= '<atom:link href="' . $this->url . '" rel="self" type="application/rss+xml" />' . "\n";

            foreach($this->tags as $k => $v) {
                $out .= "<$k>$v</$k>\n";
            }

            foreach($this->items as $item) {
                $out .= $item->out();
            }

            $out .= "</channel>\n";

            $out .= $this->footer();

            return $out;
        }

        public function serve($contentType = 'application/xml')
        {
            $xml = $this->out();
            header("Content-type: $contentType");
            echo $xml;
        }

        private function header()
        {
            $out  = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
            $out .= '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
            return $out;
        }

        private function footer()
        {
            return '</rss>';
        }

        private function fullUrl()
        {
            $s = empty($_SERVER['HTTPS']) ? '' : ($_SERVER['HTTPS'] == 'on') ? 's' : '';
            $protocol = substr(Inflector::lower($_SERVER['SERVER_PROTOCOL']), 0, strpos(Inflector::lower($_SERVER['SERVER_PROTOCOL']), '/')) . $s;
            $port = ($_SERVER['SERVER_PORT'] == '80') ? '' : (":".$_SERVER['SERVER_PORT']);
            return $protocol . "://" . $_SERVER['HTTP_HOST'] . $port . $_SERVER['REQUEST_URI'];
        }

        private function cdata($str)
        {
            if($this->useCDataTags) {
                $str = '<![CDATA[' . $str . ']]>';
            }
            return $str;
        }
    }
