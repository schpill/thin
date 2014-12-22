<?php
    namespace Thin;

    use DOMDocument;
    use DOMXpath;

    class Webquery
    {
        /**
         * $select
         * @access protected
         * @var string
         */
        protected $select;

        /**
         * $from
         * @access protected
         * @var string
         */
        protected $from;

        /**
         * $where
         * @access protected
         * @var array
         */
        protected $where;

        /**
         * $whereKey
         * @access protected
         * @var int
         */
        protected $whereKey = 0;

        /**
         * $httpclient
         * @access protected
         * @var object
         */
        protected $httpclient;

        /**
         * $dom
         * @access protected
         * @var object
         */
        protected $dom;

        public function __construct()
        {
            $this->dom = new DOMDocument;
            $this->dom->recover = true;
            $this->dom->strictErrorChecking = false;
        }

        /**
         * select()
         * Sets select object
         * @access public
         * @param string $select
         * @return \Thin\Webquery
         */
        public function select($select = "*")
        {
            $this->select = $select;

            return $this;
        }

        public function from($from)
        {
            $this->from = $from;

            return $this;
        }

        /**
         * where()
         * Sets where object
         * @access public
         * @param string $where
         * @param string $value
         * @return \Thin\Webquery
         */
        public function where($where = null, $value = null)
        {
            if (Arrays::is($this->where)) {
                $this->whereKey = count($this->where) + 1;
            }

            $this->where[$this->whereKey]['attr'] = $where;
            $this->where[$this->whereKey]['value'] = $value;

            return $this;
        }

        /**
         * execute()
         * builds and runs query, result returned as array
         * @access public
         * @return array
         */
        public function execute()
        {
            libxml_use_internal_errors(true);
            $result = [];

            $this->content = fgc($this->from);

            @$this->dom->loadHTML('<?xml encoding="UTF-8">' . $this->content);

            if (isset($this->select) && $this->select != "*") {
                $xpath = new DOMXpath($this->dom);
                $nodes = $xpath->query("//" . $this->select);
                $html = '';

                foreach ($nodes as $node) {
                    $html.= $this->removeHeaders($this->dom->saveHTML($node));
                }

                @$this->dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            }

            if (isset($this->where)) {
                $xpath = new DOMXpath($this->dom);
                foreach ($this->where as $where) {
                    $nodes = $xpath->query("//*[contains(concat(' ', @" . $where['attr'] . ", ' '), '" . $where['value'] . "')]");

                    foreach ($nodes as $node) {
                        $result[] = $this->removeHeaders($this->dom->saveHTML($node));
                    }
                }
            }

            if (!isset($this->where) && empty($result)) {
                $result[] = $this->removeHeaders($this->dom->saveHTML());
            }

            return $result;
        }

        /**
         * removeHeaders()
         * removes extra headers added by DOMDocument
         * @param string $content
         * @return string
         */
        private function removeHeaders($content)
        {
            $content = str_replace('<?xml encoding="UTF-8">', "", $content);
            $content = str_replace('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">', "", $content);
            $content = str_replace('<html><body>', "", $content);
            $content = str_replace('</body></html>', "", $content);

            return $content;
        }
    }
