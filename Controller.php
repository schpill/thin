<?php
    namespace Thin;
    class Controller
    {
        public function noRender()
        {
            $this->view->noCompiled();
            Utils::set('showStats', null);
        }

        public function getRequest()
        {
            $request = request();
            return $request;
        }
    }
