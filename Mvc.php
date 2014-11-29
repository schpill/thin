<?php
    namespace Thin;

    class Mvc
    {
        public static function router()
        {
            Timer::start();
            Config::load('application');
            static::run(static::dispatch());
        }

        private static function dispatch()
        {
            $uri    = str_replace(Config::get('application.base_uri'), '', isAke($_SERVER, 'REQUEST_URI', '/'));
            $method = strtolower(isAke($_SERVER, 'REQUEST_METHOD', 'get'));

            $routes = include(CONFIG_PATH . DS . 'routes.php');

            if (!empty($routes)) {
                foreach ($routes as $route) {
                    $methodRoute = !isset($route->method) ? 'get' : strtolower($route->method);

                    if ($route->path == $uri && $methodRoute == $method) {
                        return $route;
                    }

                    $match = static::match($route->path, $uri);

                    if (false !== $match && $methodRoute == $method) {
                        if (!empty($match)) {
                            $args = $route->args;

                            if (Arrays::is($args)) {
                                if (count($match) == count($args)) {
                                    $continue = false;
                                    $i = 1;

                                    foreach ($args as $key => $closure) {
                                        $val = $closure($match[$i]);

                                        if (false === $val) {
                                            $continue = true;
                                            break;
                                        }

                                        $_REQUEST[$key] = $val;

                                        $i++;
                                    }

                                    if (true === $continue) {
                                        continue;
                                    }
                                }
                            }
                        }

                        return $route;
                    }
                }
            }

            return static::route(['controller' => 'static', 'action' => 'is404']);
        }

        private static function match($routePath, $uri)
        {
            $path = trim(urldecode($uri), '/');

            if (!strlen($path)) {
                $path = '/';
            }

            $path       = '/' == $path[0]       ? substr($path, 1)      : $path;
            $routePath  = '/' == $routePath[0]  ? substr($routePath, 1) : $routePath;
            $pathComp   = rtrim($routePath, '/');
            $regex      = '#^' . $routePath . '$#';
            $res        = preg_match($regex, $path, $values);

            if ($res === 0) {
                return false;
            }

            foreach ($values as $i => $value) {
                if (!is_int($i) || $i === 0) {
                    unset($values[$i]);
                }
            }

            return $values;
        }

        public static function route($config = [])
        {
            return with(new Container)->populate($config);
        }

        public static function di($service = null)
        {
            return thin($service);
        }

        public static function to($name, $args = [], $dry = true)
        {
            $route = static::getRouteByName($name);

            if (null !== $route) {
                $path = $route->path;

                if (!empty($args)) {
                    foreach ($args as $key => $value) {
                        $path = strReplaceFirst('(.*)', $value, $path);
                    }
                }

                return $dry ? $path : trim(urldecode(URLSITE), '/') . $path;
            }

            return $dry ? '/' : urldecode(URLSITE);
        }

        public static function getRouteByName($name)
        {
            $routes = include(CONFIG_PATH . DS . 'routes.php');

            if (!empty($routes)) {
                foreach ($routes as $route) {
                    if ($route->name == $name) {
                        return $route;
                    }
                }
            }

            return null;
        }

        public static function tag()
        {
            return static::Di('tag');
        }

        private static function run(Container $route)
        {
            container()->setRoute($route);

            $is404 = true;

            $module     = !isset($route->module) ? 'default' : $route->module;
            $controller = $route->controller;
            $action     = $route->action;
            $render     = !isset($route->render) ? $action : $route->render;

            if ($action instanceof \Closure) {
                return call_user_func_array($action, [$route]);
            }

            if (!empty($module) && !empty($controller) && !empty($action)) {
                if (fnmatch('*-*', $action)) {
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

                $controllersDir = APPLICATION_PATH . DS . 'modules' . DS . strtolower($module) . DS . 'controllers';
                $viewsDir = APPLICATION_PATH . DS . 'modules' . DS . strtolower($module) . DS . 'views';

                $controllerFile = $controllersDir . DS . strtolower($controller) . 'Controller.php';
                $tplFile = $viewsDir . DS . Inflector::lower($controller) . DS . Inflector::lower($render) . '.phtml';

                if (File::exists($controllerFile)) {
                    require_once $controllerFile;
                    $controllerClass    = 'Thin\\' . Inflector::lower($controller) . 'Controller';
                    $controllerInstance = new $controllerClass;

                    $actions = get_class_methods($controllerClass);

                    if (Arrays::in($actionName, $actions)) {
                        $is404 = false;

                        $keyEvent = Inflector::lower($module) . '.' . Inflector::lower($controller) . '.' . $action;

                        if (File::exists($tplFile)) {
                            $view = new View($tplFile);
                            $controllerInstance->view = $view;
                        }

                        if (Arrays::in('boot', $actions)) {
                            $keyEv = $keyEvent . '.init';
                            Event::run($keyEv);
                            $controllerInstance->boot();
                        }

                        if (Arrays::in('preDispatch', $actions)) {
                            $keyEv = $keyEvent . '.before';
                            Event::run($keyEv);
                            $controllerInstance->preDispatch();
                        }

                        $controllerInstance->$actionName();

                        if (isset($controllerInstance->view)) {
                            $controllerInstance->view->render();
                        }

                        if (Arrays::in('postDispatch', $actions)) {
                            $keyEv = $keyEvent . '.after';
                            Event::run($keyEv);
                            $controllerInstance->postDispatch();
                        }

                        if (Arrays::in('exit', $actions)) {
                            $keyEv = $keyEvent . '.done';
                            Event::run($keyEv);
                            $controllerInstance->exit();
                        }
                    }
                }
            }

            if (true === $is404) {
                return static::run(static::route(['controller' => 'static', 'action' => 'is404']));
            } else {
                exit;
            }
        }
    }
