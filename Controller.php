<?php
    namespace Thin;
    class Controller
    {
        public function __construct()
        {
            $this->view = view();
        }

        public function noRender()
        {
            $this->view->noCompiled();
            Utils::set('showStats', null);
        }

        public function getRequest()
        {
            return request();
        }

        public function forward($action = 'index', $controller = null, $module = null)
        {
            $actualRoute = container()->getRoute();
            if (null === $actualRoute && null === $controller && null === $module) {
                throw new Exception('Invalid forward.');
            }
            if (null === $module) {
                $module = $actualRoute->getModule();
            }
            if (null === $controller) {
                $controller = $actualRoute->getController();
            }
            $route = new Container;
            $route->setModule($module)->setController($controller)->setAction($action);
            context()->dispatch($route);
            exit;
        }

        public function route()
        {
            return container()->getRoute();
        }

        public function action()
        {
            return $this->route()->getAction();
        }

        public function isPost($except = array())
        {
            return context()->isPost($except);
        }
    }
