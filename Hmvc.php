<?php
    namespace Thin;

    class Hmvc
    {
        public static function execute($module, $controller, $action, $args = [])
        {
            $dirModule = APPLICATION_PATH . DS . 'modules' . DS . SITE_NAME . DS . Inflector::lower($module);

            if (!is_dir($dirModule)) {
                throw new Exception("The directory '$dirModule' does not exist.");
            }

            $dirController = $dirModule . DS . 'controllers';

            if (!is_dir($dirController)) {
                throw new Exception("The directory '$dirController' does not exist.");
            }

            $controllerFile = $dirController . DS . Inflector::lower($controller) . 'Controller.php';

            if (!File::exists($controllerFile)) {
                throw new Exception("The file '$controllerFile' does not exist.");
            }

            require_once $controllerFile;

            $oldRoute = container()->getRoute();

            container()->setRoute(
                with(new Container)->setModule($module)->setController($controller)->setAction($action)
            );

            $controllerClass    = 'Thin\\' . Inflector::lower($controller) . 'Controller';
            $controllerInstance = new $controllerClass;

            $actions            = get_class_methods($controllerClass);

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

            if (!Arrays::in($actionName, $actions)) {
                throw new Exception("The action '$actionName' does not exist in $controllerFile.");
            }

            if (Arrays::in('init', $actions)) {
                $controllerInstance->init();
            }

            if (Arrays::in('preDispatch', $actions)) {
                $controllerInstance->preDispatch();
            }

            $res = call_user_func_array([$controllerInstance, $actionName], $args);

            if (Arrays::in('postDispatch', $actions)) {
                $controllerInstance->preDispatch();
            }

            container()->setRoute($oldRoute);

            return $res;
        }
    }
