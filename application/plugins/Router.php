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
    }
