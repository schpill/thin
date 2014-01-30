<?php
    namespace Thin;

    class Router
    {
        private static $_uri;
        private static $_values = array();
        /**
         * URI delimiter
         */
        const URI_DELIMITER = '/';

        public static function dispatch()
        {
            static::$_uri = $uri = trim($_SERVER['REQUEST_URI'], static::URI_DELIMITER);
            $defaultRoute = static::defaultRoute();
            if (null !== $defaultRoute) {
                return;
            }
            if (true === container()->getMultiSite()) {
                $file           = APPLICATION_PATH . DS . 'config' . DS . SITE_NAME . DS . 'routes.php';
            } else {
                $file           = APPLICATION_PATH . DS . 'config' . DS . 'routes.php';
            }
            $configRoutes   = include($file);
            $routes         = $configRoutes['collection'];
            $routes         += null !== container()->getRoutes() ? container()->getRoutes() : array();
            foreach ($routes as $route) {
                if (!$route instanceof Container) {
                    continue;
                }
                $path = $route->getPath();
                if ($path == $uri) {
                    return static::make($route);
                }
            }
            foreach ($routes as $route) {
                if (!$route instanceof Container) {
                    continue;
                }
                if (!strlen($route->getPath())) {
                    continue;
                }
                $matched = static::match($route->getPath());
                if (false === $matched) {
                    continue;
                } else {
                    return static::make($route);
                }
            }

            static::is404();

        }

        private static function match($pathComp)
        {
            $path = trim(urldecode(static::$_uri), static::URI_DELIMITER);
            $regex = '#^' . $pathComp . '#i';
            $res = preg_match($regex, $path, $values);

            if ($res === 0) {
                return false;
            }

            foreach ($values as $i => $value) {
                if (!is_int($i) || $i === 0) {
                    unset($values[$i]);
                }
            }

            static::$_values = $values;

            return $values;
        }

        private static function make($route)
        {
            \u::set('appDispatch', $route);
            if (null !== $route->getParam1()) {
                static::assign($route);
            }
        }

        private static function assign($route)
        {
            foreach (static::$_values as $key => $value) {
                $getter = 'getParam' . $key;
                $requestKey = $route->$getter();
                $_REQUEST[$requestKey] = $value;
            }
            $tab = explode('?', $_SERVER['REQUEST_URI']);
            if (count($tab) > 1) {
                list($start, $query) = explode('?', $_SERVER['REQUEST_URI']);
                if (strlen($query)) {
                    $str = parse_str($query, $output);
                    if (count($output)) {
                        foreach ($output as $k => $v) {
                            $_GET[$k] = $v;
                        }
                    }
                }
            }
        }

        private static function is404()
        {
            header('HTTP/1.0 404 Not Found');
            die(config('html_not_found'));
            $dispatch = new Dispatch;
            $dispatch->setModule('www');
            $dispatch->setController('static');
            $dispatch->setAction('is404');
            Utils::set('appDispatch', $dispatch);
        }

        public static function isError()
        {
            $dispatch = new Dispatch;
            $dispatch->setModule('www');
            $dispatch->setController('static');
            $dispatch->setAction('is-error');
            Utils::set('appDispatch', $dispatch);
        }

        private static function defaultRoute()
        {
            $tab            = explode('/', substr(static::$_uri, 1));
            if (count($tab) > 1) {
                if (3 != count($tab)) {
                    $module         = container()->getConfig()->getDefaultModule();
                    $module         = Inflector::lower($module);
                    $controller     = Inflector::lower(Arrays::first($tab));
                    $action         = $tab[1];
                } else {
                    list($module, $controller, $action) = $tab;
                    $module         = Inflector::lower($module);
                    $controller     = Inflector::lower($controller);
                    $action         = Inflector::lower($action);
                }
                $action         = repl(array('.html', '.php', '.asp', '.jsp', '.cfm', '.py', '.pl'), array('', '', '', '', '', '', ''), $action);
                if (true === container()->getMultiSite()) {
                    $moduleDir      = APPLICATION_PATH . DS . SITE_NAME . DS . 'modules' . DS . $module;
                } else {
                    $moduleDir      = APPLICATION_PATH . DS . 'modules' . DS . $module;
                }
                $controllerDir  = $moduleDir . DS . 'controllers';
                $controllerFile = $controllerDir . DS . $controller . 'Controller.php';
                if (true === File::exists($controllerFile)) {
                    require_once $controllerFile;
                    $controllerClass    = 'Thin\\' . $controller . 'Controller';
                    $controllerInstance = new $controllerClass;
                    $actions            = get_class_methods($controllerInstance);
                    $actionName         = $action . 'Action';

                    if (Arrays::inArray($actionName, $actions)) {
                        $dispatch = new Dispatch;
                        $dispatch->setModule($module);
                        $dispatch->setController($controller);
                        $dispatch->setAction(Inflector::uncamelize($action, '-'));
                        Utils::set('appDispatch', $dispatch);
                        return true;
                    }
                }
            }
            return null;
        }

        public static function language()
        {
            $route                  = Utils::get('appDispatch');
            $session                = session('website');
            $language               = $session->getLanguage();
            if (null === $language) {
                $language           = null === $route->getLanguage() ? container()->getConfig()->getDefaultLanguage() : $route->getLanguage();
                $session->setLanguage($language);
            }
            $module                 = $route->getModule();
            $controller             = $route->getController();
            $action                 = $route->getAction();

            $module                 = Inflector::lower($module);
            $controller             = Inflector::lower($controller);
            $action                 = Inflector::lower($action);

            $config                 = array();
            $config['language']     = $language;
            $config['module']       = $module;
            $config['controller']   = $controller;
            $config['action']       = $action;

            $configLanguage         = new configLanguage();
            $configLanguage->populate($config);


            container()->setLanguage(new Language($configLanguage));
        }

        public static function run()
        {
            Request::$route = $route = Utils::get('appDispatch');
            container()->setRoute($route);
            $module         = $route->getModule();
            $controller     = $route->getController();
            $action         = $route->getAction();
            $alert          = $route->getAlert();

            $module         = Inflector::lower($module);
            $controller     = Inflector::lower($controller);
            $action         = Inflector::lower($action);
            if (true === container()->getMultiSite()) {
                $moduleDir = APPLICATION_PATH . DS . SITE_NAME . DS . 'modules' . DS . $module;
            } else {
                $moduleDir = APPLICATION_PATH . DS . 'modules' . DS . $module;
            }
            if (!is_dir($moduleDir)) {
                throw new Exception("The module '$module' does not exist.");
            }
            $controllerDir = $moduleDir . DS . 'controllers';
            if (!is_dir($controllerDir)) {
                throw new Exception("The controller '$controller' does not exist.");
            }

            $controllerFile = $controllerDir . DS . $controller . 'Controller.php';
            if (!File::exists($controllerFile)) {
                throw new Exception("The controller '$controllerFile' does not exist.");
            }
            require_once $controllerFile;

            $controllerClass    = 'Thin\\' . $controller . 'Controller';
            $controller         = new $controllerClass;
            $controller->view   = new View($route->getView());

            if (null !== $alert) {
                $controller->view->alert($alert);
            }

            container()->setController($controller);

            $actions = get_class_methods($controllerClass);
            container()->setAction($action);

            if (strstr($action, '-')) {
                $words = explode('-', $action);
                $newAction = '';
                for ($i = 0 ; $i < count($words) ; $i++) {
                    $word = trim($words[$i]);
                    if ($i > 0) {
                        $word = ucfirst($word);
                    }
                    $newAction .= $word;
                }
                $action = $newAction;
            }

            $actionName = $action . 'Action';

            if (Arrays::inArray('init', $actions)) {
                $controller->init();
            }
            if (Arrays::inArray('preDispatch', $actions)) {
                $controller->preDispatch();
            }

            if (!Arrays::inArray($actionName, $actions)) {
                throw new Exception("The action '$actionName' does not exist.");
            }

            $controller->$actionName();

            $controller->view->render();

            if (Arrays::inArray('postDispatch', $actions)) {
                $controller->postDispatch();
            }

            /* stats */
            if (null !== Utils::get("showStats")) {
                echo View::showStats();
            }
        }

        public static function redirect($url)
        {
            Utils::go($url);
        }

        private static function cmsRoute()
        {
            return null;
        }
    }
