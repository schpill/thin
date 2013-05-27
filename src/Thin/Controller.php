<?php
    namespace Thin;
    class Controller
    {
        public function noRender()
        {
            $this->view->noCompiled();
            u::clearEvent('bootstrap.finished');
        }

        public function getRequest()
        {
            $request = request();
            return $request;
        }
    }
