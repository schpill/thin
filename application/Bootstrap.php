<?php
    namespace Thin;
    use Symfony\Component\HttpFoundation\ThinRequest as ThinRequest;

    class Bootstrap
    {
        private static $app;

        public static function init()
        {
            session_start();

            Request::$foundation = ThinRequest::createFromGlobals();

            define('NL', "\n");
            define('ISAJAX', \i::lower(getenv('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest');

            Utils::cleanCache();
            $logger = new Log();
            $app = new Application;

            $app['logger'] = $logger;

            \u::set('app', $app);

            static::$app = $app;

            static::loadConfigs();
            static::dispatch();
            static::test();
            static::run();
        }

        private static function loadConfigs()
        {
            Config::load('application');
            Config::load('models', false);
            Config::load('routes', false);
        }

        private static function dispatch()
        {
            $router = plugin('router');
            $router::dispatch();
        }

        public static function run()
        {
            Request::$route = $route = \u::get('appDispatch');
            $module = $route->getModule();
            $controller = $route->getController();
            $action = $route->getAction();

            $moduleDir = APPLICATION_PATH . DS . 'modules' . DS . \i::lower($module);
            if (!is_dir($moduleDir)) {
                throw new Exception("The module '$module' does not exist.");
            }
            $controllerDir = $moduleDir . DS . 'controllers';
            if (!is_dir($controllerDir)) {
                throw new Exception("The controller '$controller' does not exist.");
            }

            $controllerFile = $controllerDir . DS . \i::lower($controller) . 'Controller.php';
            if (!file_exists($controllerFile)) {
                throw new Exception("The controller '$controllerFile' does not exist.");
            }
            require_once $controllerFile;

            $controllerClass = 'Thin\\' . \i::lower($controller) . 'Controller';
            $controller = new $controllerClass;

            $controller->view = new View;
            $actions = get_class_methods($controllerClass);

            if (in_array('init', $actions)) {
                $controller->init();
            }
            if (in_array('preDispatch', $actions)) {
                $controller->preDispatch();
            }

            if (strstr($action, '-')) {
                $words = explode('-', $action);
                $newAction = '';
                for ($i = 0 ; $i < count($words) ; $i++) {
                    $word = trim($words[$i]);
                    if ($i > 0) {
                        $word = ucfirst(\i::lower($word));
                    }
                    $newAction .= $word;
                }
                $action = $newAction;
            }

            $actionName = $action . 'Action';

            if (!in_array($actionName, $actions)) {
                throw new Exception("The action '$actionName' does not exist.");
            }

            $controller->$actionName();

            $controller->view->render();

            if (in_array('postDispatch', $actions)) {
                $controller->postDispatch();
            }
        }

        private static function test()
        {

        }
    }
