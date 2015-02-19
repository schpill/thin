<?php
    namespace Thin;

    class Route
    {
        public static function dispatchBundle(Container $route)
        {
            $bundle         = $route->getBundle();
            $controller     = $route->getController();
            $action         = $route->getAction();
            $path           = realpath(APPLICATION_PATH . '/../');
            $bundle         = ucfirst(Inflector::lower($bundle));

            $viewsDir       = $path . DS . 'bundles' . DS . $bundle . DS . 'views';
            $controllersDir = $path . DS . 'bundles' . DS . $bundle . DS . 'controllers';

            $tpl            = $viewsDir . DS . Inflector::lower($controller) . ucfirst(Inflector::lower($action)) . '.phtml';
            $controllerFile = $controllersDir . DS . Inflector::lower($controller) . '.php';

            $file   = $path . DS . 'bundles' . DS . $bundle . DS . $bundle . '.php';
            if (File::exists($file)) {
                $getNamespaceAndClassNameFromCode = getNamespaceAndClassNameFromCode(fgc($file));
                list($namespace, $class) = $getNamespaceAndClassNameFromCode;
                if (File::exists($controllerFile)) {
                    if (File::exists($tpl)) {
                        $view = new View($tpl);
                        container()->setView($view);
                    }
                    require_once $controllerFile;
                    $controllerClass    = $namespace . '\\' . Inflector::lower($controller) . 'Controller';
                    $controller         = new $controllerClass;
                    if (File::exists($tpl)) {
                        $controller->view   = $view;
                    }
                    container()->setController($controller);
                    $actions = get_class_methods($controllerClass);

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

                    if (Arrays::in('init', $actions)) {
                        $controller->init();
                    }

                    if (Arrays::in('preDispatch', $actions)) {
                        $controller->preDispatch();
                    }

                    if (!Arrays::in($actionName, $actions)) {
                        throw new Exception("The action '$actionName' does not exist.");
                    }

                    $controller->$actionName();
                    if (File::exists($tpl)) {
                        $controller->view->render();
                    }
                    /* stats */
                    if (File::exists($tpl) && null === container()->getNoShowStats() && null === $route->getNoShowStats()) {
                        echo View::showStats();
                    }
                    if (Arrays::in('postDispatch', $actions)) {
                        $controller->preDispatch();
                    }
                    if (Arrays::in('exit', $actions)) {
                        $controller->exit();
                    }
                } else {
                    context()->is404();
                }
            } else {
                context()->is404();
            }
        }

        public static function assetBundle($path = 'css/style.css')
        {
            $route = Utils::get('appDispatch');
            $bundle = $route->getBundle();
            if (!is_null($bundle)) {
                $bpath = realpath(APPLICATION_PATH . '/../');
                $bundle = ucfirst(Inflector::lower($bundle));
                $assetsDir = $bpath . DS . 'bundles' . DS . $bundle . DS . 'public';
                $file = $assetsDir . DS . $path;
                if (File::exists($file)) {
                    $url = URLSITE . 'bundles/' . $bundle . '/public/' . $path;
                }
            }
            return URLSITE . '/' . $path;
        }

        public static function addBundleRoute($bundle, Container $route)
        {
            if (strstr($bundle, '\\')) {
                list($ns, $bundle) = explode('\\', $bundle, 2);
            }
            return $route->setBundle($bundle)->assign();
        }
    }
