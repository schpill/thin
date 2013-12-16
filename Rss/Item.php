<?php
    /**
     * Item class
     * @author      Gerald Plusquellec
     */

    namespace Thin\Rss;
    class Item
    {
        public $title;
        public $link;
        public $description;
        public $pubDate;
        public $guid;
        public $tags;
        public $enclosureUrl;
        public $enclosureType;
        public $enclosureLength;
        public $useCDataTags;

        public function __construct()
        {
            $this->useCDataTags = true;
            $this->tags = array();
            $this->setPubDate();
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

        public function out()
        {
            $bad         = array('&', '<');
            $good        = array('&#x26;', '&#x3c;');
            $title       = repl($bad, $good, $this->title);

            $out  = "<item>\n";
            $out .= "<title>" . $title . "</title>\n";
            $out .= "<link>" . $this->link . "</link>\n";
            $out .= "<description>" . $this->cdata($this->description) . "</description>\n";
            $out .= "<pubDate>" . $this->pubDate . "</pubDate>\n";

            if(is_null($this->guid)) {
                $this->guid = $this->link;
            }

            $out .= "<guid>" . $this->guid . "</guid>\n";

            if(!is_null($this->enclosureUrl)) {
                $out .= "<enclosure url='{$this->enclosureUrl}' length='{$this->enclosureLength}' type='{$this->enclosureType}' />\n";
            }

            foreach($this->tags as $k => $v) {
                $out .= "<$k>$v</$k>\n";
            }

            $out .= "</item>\n";
            return $out;
        }

        public function enclosure($url, $type, $length)
        {
            $this->enclosureUrl    = $url;
            $this->enclosureType   = $type;
            $this->enclosureLength = $length;
        }

        private function cdata($str)
        {
            if($this->useCDataTags) {
                $str = '<![CDATA[' . $str . ']]>';
            }
            return $str;
        }
    }
