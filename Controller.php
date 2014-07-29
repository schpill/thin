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

        public function forward($route, $alert = null)
        {
            $oldRoute   = container()->getRoute();
            $module     = null !== $route->getModule()      ? $route->getModule()       : $oldRoute->getModule();
            $controller = null !== $route->getController()  ? $route->getController()   : $oldRoute->getController();
            $action     = null !== $route->getAction()      ? $route->getAction()       : $oldRoute->getAction();
            $params     = null !== $route->getParams()      ? $route->getParams()       : array();

            if (count($params)) {
                foreach ($params as $key => $value) {
                    $_REQUEST[$key] = $value;
                }
            }

            $dispatch = new Dispatch;
            $dispatch->setModule($module);
            $dispatch->setController($controller);
            $dispatch->setAction($action);
            $dispatch->setAlert($alert);
            Utils::set('appDispatch', $dispatch);

            Router::language();
            Router::run();

            exit;
        }

        public function __get($key)
        {
            if (Tool::registered($key)) {
                return Tool::resolve($key);
            }
        }
    }
