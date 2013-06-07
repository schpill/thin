<?php
    namespace Thin\Plugin;
    use Thin\Config as Config;

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
            static::$_uri = $uri = $_SERVER['REQUEST_URI'];
            $defaultRoute = static::defaultRoute();
            if (null !== $defaultRoute) {
                return;
            }
            $file = APPLICATION_PATH . DS . 'config' . DS . 'routes.php';
            $configRoutes = include($file);
            $routes = $configRoutes['collection'];
            foreach ($routes as $route) {
                $path = $route->getPath();
                if ($path == $uri) {
                    return static::make($route);
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
                $setter = $route->$getter();
                $_REQUEST[$setter] = $value;
            }
        }

        private static function is404()
        {
            $dispatch = new Dispatch;
            $dispatch->setModule('www');
            $dispatch->setController('static');
            $dispatch->setAction('is404');
            \u::set('appDispatch', $dispatch);
        }

        public static function isError()
        {
            $dispatch = new Dispatch;
            $dispatch->setModule('www');
            $dispatch->setController('static');
            $dispatch->setAction('is-error');
            \u::set('appDispatch', $dispatch);
        }

        private static function defaultRoute()
        {
            $tab            = explode('/', substr(static::$_uri, 1));
            if (count($tab) > 1) {
                $module         = \Thin\Config::get('application.application.defaultModule');
                $controller     = \Thin\Inflector::lower(current($tab));
                $action         = repl(array('.html', '.php', '.asp', '.jsp', '.cfm', '.py', '.pl'), array('', '', '', '', '', '', ''), $tab[1]);
                $moduleDir      = APPLICATION_PATH . DS . 'modules' . DS . \Thin\Inflector::lower($module);
                $controllerDir  = $moduleDir . DS . 'controllers';
                $controllerFile = $controllerDir . DS . \Thin\Inflector::lower($controller) . 'Controller.php';
                if (true === \Thin\File::exists($controllerFile)) {
                    require_once $controllerFile;
                    $controllerClass    = 'Thin\\' . \Thin\Inflector::lower($controller) . 'Controller';
                    $controllerInstance = new $controllerClass;
                    $actions            = get_class_methods($controllerInstance);
                    $actionName         = $action . 'Action';

                    if (\Thin\Arrays::inArray($actionName, $actions)) {
                        $dispatch = new Dispatch;
                        $dispatch->setModule($module);
                        $dispatch->setController($controller);
                        $dispatch->setAction(\Thin\Inflector::uncamelize($action, '-'));
                        \Thin\Utils::set('appDispatch', $dispatch);
                        return true;
                    }
                }
            }
            return null;
        }
    }
