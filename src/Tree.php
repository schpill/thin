<?php
    namespace Thin;
    class Tree
    {
        public $tree;
        public $nodes = array();
        public $node;

        public function __construct($name)
        {
            $tree = o($name);
            $tree->setSelf($this);
            $this->tree = $tree;
        }

        public function node($name, $value = null)
        {
            $node = new self($name);
            $node->tree->setFather($this);
            $this->node = $this->nodes[$name] = $node;
            return $this;
        }

        public function children()
        {
            return $this->node->tree;
        }

        public function getTree()
        {
            return $this->tree;
        }

        public function getNode($name)
        {
            return ake($name, $this->nodes) ? $this->nodes[$name] : null;
        }

        public function chain()
        {
            return $this;
        }
    }
