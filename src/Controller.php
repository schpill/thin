<?php
    namespace Thin;

    use Closure;
    use Mvc\Router as MVCRouter;

    class Controller
    {
        public function __construct()
        {
            $this->view     = view();
            $this->flash    = function() {return new \Phalcon\Flash\Direct();};
            $this->filter   = function() {return new \Phalcon\Filter();};
            $this->request  = new \Phalcon\Http\Request();
            $this->response = new \Phalcon\Http\Response();
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

        public function redirect($action = 'index', $args = [], $controller = null, $module = null)
        {
            if (is_null($controller) && is_null($module)) {
                $url = urlAction($action);
            } elseif (is_null($module) && !is_null($controller)) {
                $url = URLSITE . $controller . '/' . $action;
            } elseif (!is_null($module) && !is_null($controller)) {
                $url = URLSITE . $module . '/' . $controller . '/' . $action;
            } else {
                $url = URLSITE;
            }

            if (count($args)) {
                foreach ($args as $key => $value) {
                    $url .= "/$key/$value";
                }
            }

            Utils::go($url);
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

        public function close()
        {
            $html = '<body onload="self.close();">';

            die($html);
        }

        public function handle(Closure $closure, $args = [], $exit = true)
        {
            $res = call_user_func_array($closure, $args);

            if ($exit) {
                die($res);
            } else {
                return $res;
            }
        }

        public function go($routeName)
        {
            MVCRouter::forward();
        }

        public function set($var, $what)
        {
            $this->$var = $what;
        }

        public function __set($var, $what)
        {
            $this->$var = $what;
        }

        public function __isset($var)
        {
            return isset($this->$var);
        }

        public function __get($var)
        {
            return $this->$var;
        }

        public function __call($method, $args)
        {
            if (isset($this->$method)) {
                if (is_callable($this->$method)) {
                    return call_user_func_array($this->$method, $args);
                }
            }
        }
    }
