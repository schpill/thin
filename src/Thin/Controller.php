<?php
    namespace Thin;
    class Controller
    {
        public function noRender()
        {
            $this->view->noCompiled();
            \u::set('showStats', null);
        }

        public function getRequest()
        {
            $request = request();
            return $request;
        }
    }
