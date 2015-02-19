<?php
    /**
     * Form View class
     * @author      Gerald Plusquellec
     */
    namespace Thin\Form
    class View
    {
        private $_view;
        private $_render;
        private $_content = array();

        function __construct()
        {
            $this->_view = new \Thin\View('form');
            $this->_view->noCompiled();
            return $this;
        }

        public function __call($method, $parameters)
        {
            $html = call_user_func_array(array('Thin\\Form', $method), $parameters);
            $this->add($html);
            return $this;
        }

        public function render()
        {
            $this->_view->content = implode('', $this->_content);
            $this->_render = $this->_view->render(null, false);
            return $this;
        }

        public function getRender()
        {
            $this->render();
            return $this->_render;
        }

        public function add($html)
        {
            array_push($this->_content, $html);
            return $this;
        }

        public function __destruct()
        {
            unlink($this->_view->_viewFile);
        }
    }
