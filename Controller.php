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

        public function __get($key)
        {
            if (Tool::registered($key)) {
                return Tool::resolve($key);
            }
        }
    }
