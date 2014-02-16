<?php
    namespace Thin;
    use Closure;

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

            $containerRoute = static::containerRoute();
            if (null !== $containerRoute) {
                return;
            }

            $defaultRoute = static::defaultRoute();
            if (null !== $defaultRoute) {
                return;
            }

            $entities = container()->getEntities();

            /* Pages non routées */
            if (true === container()->getMultiSite() && !empty($entities)) {
                $url        = substr($_SERVER['REQUEST_URI'], 1);
                $db         = new Querydata('page');
                $res        = $db->where('is_home = ' . getBool('true')->getId())->get();
                $home       = $db->first($res);
                $_homeUrl   = Cms::__($home->getUrl());
                $homeUrl    = null !== $_homeUrl ? $_homeUrl : 'home';
                $url        = !strlen($url) ? $homeUrl : $url;
                $pages      = Cms::getPages();
                $cmsRoutes  = array();

                $lngs       = explode(',', cms_option('page_languages'));

                if (count($pages)) {
                    foreach ($pages as $pageTmp) {
                        if (1 < count($lngs)) {
                            $urlTab = $pageTmp->getUrl();
                            foreach($lngs as $lng) {
                                if (ake($lng, $urlTab)) {
                                    $cmsRoutes[$urlTab[$lng]] = $pageTmp;
                                }
                            }
                        } else {
                            $cmsRoutes[Cms::__($pageTmp->getUrl())] = $pageTmp;
                        }
                    }
                }

                $found = Arrays::exists($url, $cmsRoutes);

                if (false === $found) {
                    $found = Cms::match($cmsRoutes);
                }
                if (true === $found && ake($url, $cmsRoutes)) {
                    $page      = $cmsRoutes[$url];
                    $status    = Inflector::lower($page->getStatuspage()->getName());
                    $dateDepub = $page->getDateOut();
                    $now       = time();

                    $continue  = true;

                    if (strlen($dateDepub)) {
                        list($d, $m, $y)    = explode('-', $dateDepub, 3);
                        $dateDepub          = "$y-$m-$d";
                        $dateDepub          = new Date($dateDepub);
                    }

                    if ($dateDepub instanceof Date) {
                        $ts = $dateDepub->getTimestamp();
                        if ($ts < $now) {
                            $page = $cmsRoutes['home'];
                        }
                    }

                    if ('offline' == $status) {
                        $continue = false;
                    } else {
                        if ('online' != $status) {
                            $page = ake($status, $cmsRoutes) ? $cmsRoutes[$status] : $cmsRoutes['home'];
                        }
                    }
                    if (true === $continue) {
                        return static::isCms($page);
                    }
                }
            }

            if (true === container()->getMultiSite()) {
                $file = APPLICATION_PATH . DS . 'config' . DS . SITE_NAME . DS . 'routes.php';
            } else {
                $file = APPLICATION_PATH . DS . 'config' . DS . 'routes.php';
            }
            if (File::exists($file)) {
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
            } else {
                static::make(container()->getNotFoundRoute());
                return;
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
            Utils::set('appDispatch', $route);
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

        private static function isCms($page)
        {
            $dispatch = new Dispatch;
            $dispatch->setModule('www');
            $dispatch->setController('static');
            $dispatch->setAction($page->getTemplate());
            Utils::set('appDispatch', $dispatch);
            container()->setPage($page);
        }

        public static function isError()
        {
            $dispatch = new Dispatch;
            $dispatch->setModule('www');
            $dispatch->setController('static');
            $dispatch->setAction('is-error');
            Utils::set('appDispatch', $dispatch);
        }

        private static function containerRoute()
        {
            $routes = container()->getMapRoutes();
            if (!empty($routes)) {
                foreach ($routes as $name => $route) {
                    if (404 == $name) {
                        container()->setNotFoundRoute($route);
                    }
                    if (!$route instanceof Container) {
                        continue;
                    }
                    $path = $route->getPath();
                    if (strlen($path) && $path == static::$_uri) {
                        static::make($route);
                        return true;
                    }
                    if (!strlen($path) && !strlen(static::$_uri)) {
                        static::make($route);
                        return true;
                    }
                    $matched = static::match($path);
                    if (false === $matched || !count($matched)) {
                        continue;
                    } else {
                        static::make($route);
                        return true;
                    }
                }
            }
            return null;
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
            $isCMS      = null !== container()->getPage();
            $session    = session('web');
            if (true === $isCMS) {
                if (count($_POST)) {
                    if (ake('cms_lng', $_POST)) {
                        $session->setLanguage($_POST['cms_lng']);
                    } else {
                        $language = $session->getLanguage();
                        $language = null === $language ? Cms::getOption('default_language') : $language;
                        $session->setLanguage($language);
                    }
                } else {
                    $language = $session->getLanguage();
                    $language = null === $language ? Cms::getOption('default_language') : $language;
                    $session->setLanguage($language);
                }
            } else {
                $route                  = Utils::get('appDispatch');
                $language               = $session->getLanguage();
                if (null === $language || $language != $route->getLanguage()) {
                    $language           = null === $route->getLanguage() ? options()->getDefaultLanguage() : $route->getLanguage();
                    $session->setLanguage($language);
                }
                $module                 = $route->getModule();
                $controller             = $route->getController();
                $action                 = $route->getAction();

                $module                 = is_string($action) ? Inflector::lower($module) : $module;
                $controller             = is_string($action) ? Inflector::lower($controller) : $controller;
                $action                 = is_string($action) ? Inflector::lower($action) : $action;

                $config                 = array();
                $config['language']     = $language;
                $config['module']       = $module;
                $config['controller']   = $controller;
                $config['action']       = $action;

                $configLanguage         = new configLanguage();
                $configLanguage->populate($config);


                container()->setLanguage(new Language($configLanguage));
            }
        }

        public static function run()
        {
            Request::$route = $route = Utils::get('appDispatch');
            container()->setRoute($route);
            $render         = $route->getRender();
            $tplDir         = $route->getTemplateDir();
            $module         = $route->getModule();
            $controller     = $route->getController();
            $action         = $route->getAction();
            $alert          = $route->getAlert();
            $page           = container()->getPage();
            $isCms          = !empty($page);

            if (!empty($render)) {
                $tplMotor = $route->getTemplateMotor();
                $tplDir = empty($tplDir) ? APPLICATION_PATH . DS . SITE_NAME . DS . 'app' . DS . 'views' : $tplDir;
                $tpl = $tplDir . DS . $render . '.phtml';
                if (File::exists($tpl)) {
                    if ('Twig' == $tplMotor) {
                        if (!class_exists('Twig_Autoloader')) {
                            require_once 'Twig/Autoloader.php';
                        }

                        $tab    = explode(DS, $tpl);
                        $file   = Arrays::last($tab);

                        $path   = repl(DS . $file, '', $tpl);

                        $loader = new \Twig_Loader_Filesystem($path);
                        $view   = new \Twig_Environment(
                            $loader,
                            array(
                                'cache'             => CACHE_PATH,
                                'debug'             => false,
                                'charset'           => 'utf-8',
                                'strict_variables'  => false
                            )
                        );
                        container()->setView($view);
                        if ($action instanceof Closure) {
                            $action($view);
                        }
                        $params = null === container()->getViewParams() ? array() : container()->getViewParams();
                        echo $view->render($file, $params);
                        /* stats */
                        if (null === container()->getNoShowStats() && null === $route->getNoShowStats()) {
                            echo View::showStats();
                        }
                    } else {
                        $view = new View($tpl);
                        container()->setView($view);
                        if ($action instanceof Closure) {
                            $action($view);
                        }
                        $view->render();
                        /* stats */
                        if (null === container()->getNoShowStats() && null === $route->getNoShowStats()) {
                            echo View::showStats();
                        }
                    }
                    return;
                }
            }

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
            if (true === $isCms) {
                if (!Arrays::inArray($action, $actions)) {
                    $action = 'page';
                }
            }
            container()->setAction($action);

            if (strstr($action, '-')) {
                $words = explode('-', $action);
                $newAction = '';
                for ($i = 0; $i < count($words); $i++) {
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

        public static function ssl()
        {
            Utils::go(repl('http:', 'https:', trim(URLSITE, '/')) . Request::uri());
        }
    }
