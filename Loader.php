<?php
    clearstatcache();
    if (true === $debug) {
        error_reporting(-1);

        set_exception_handler(function($exception) {
            showException($exception);
        });

        set_error_handler(function($type, $message, $file, $line) {
            $exception = new ErrorException($message, $type, 0, $file, $line);
            showException($exception);
        });

        register_shutdown_function(function() {
            $error = error_get_last();
            if (null !== $error) {
                var_dump($error, true);
            }
        });
    }

    require_once 'Helper.php';
    require_once 'Autoloader.php';
    require_once 'Swift/swift_required.php';
    require_once 'facebook/facebook.php';

    define('MB_STRING', (int) function_exists('mb_get_info'));
    Thin\Autoloader::registerNamespace('ThinEntity',    APPLICATION_PATH . DS . 'entities');
    Thin\Autoloader::registerNamespace('ThinHelper',    APPLICATION_PATH . DS . 'helpers');
    Thin\Autoloader::registerNamespace('ThinModel',     APPLICATION_PATH . DS . 'models');
    Thin\Autoloader::registerNamespace('ThinService',   APPLICATION_PATH . DS . 'services');
    Thin\Autoloader::registerNamespace('ThinPlugin',    APPLICATION_PATH . DS . 'plugins');
    Thin\Autoloader::registerNamespace('ThinForm',      APPLICATION_PATH . DS . 'forms');
    Thin\Autoloader::registerNamespace('ThinProject',   APPLICATION_PATH . DS . 'lib');

    spl_autoload_register('Thin\\Autoloader::autoload');

    container();
    $core = context('core');

    define('THINSTART', microtime());
    if (Thin\Arrays::exists('SERVER_NAME', $_SERVER)) {
        $protocol = 'http';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
            $protocol = 'https';
        }

        container()->setProtocol($protocol);

        $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . "/";

        if (strstr($urlSite, '//')) {
            $urlSite = repl('//', '/', $urlSite);
            $urlSite = repl($protocol . ':/', $protocol . '://', $urlSite);
        }

        if (Thin\Inflector::upper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $tab = explode('\\', $urlSite);
            $r = '';
            foreach ($tab as $c => $v) {
                $r .= $v;
            }
            $r = repl('//', '/', $r);
            $r = repl($protocol . ':/', $protocol . '://', $r);
            $urlSite = $r;
        }
        container()->setNonRoot(false);


        if (null !== request()->getFromHtaccess()) {
            if ('true' == request()->getFromHtaccess() && !getenv('FROM_ROOT')) {
                $dir                        = $_SERVER['SCRIPT_NAME'];
                $htaccessDir                = repl(DS . 'web' . DS . 'index.php', '', $dir);
                $uri                        = $_SERVER['REQUEST_URI'];
                $uri                        = repl($htaccessDir . DS, '', $uri);
                $_SERVER['REQUEST_URI']     = DS . $uri;
                $urlSite                    .= repl(DS, '', $htaccessDir) . DS;
                container()->setNonRoot(true);
            }
        }

        Thin\Utils::set("urlsite", $urlSite);
        define('URLSITE', $urlSite);
        container()->setUrlsite(URLSITE);
        $core->setIsCli(false)->setUrlsite(URLSITE);
    } else {
        $core->setIsCli(true);
    }

