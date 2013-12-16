<?php
    if (true === $debug) {
        error_reporting(-1);

        set_exception_handler(function($exception) {
            var_dump($exception);
            exit;
        });
        set_error_handler(function($type, $message, $file, $line) {
            $exception = new \ErrorException($message, $type, 0, $file, $line);
            var_dump($exception);
            exit;
        });
        register_shutdown_function(function() {
            $error = error_get_last();
            if (null !== $error) {
                var_dump($error, true);
                exit;
            }
        });
    }

    require_once 'Helper.php';
    require_once 'Autoloader.php';

    define('MB_STRING', (int) function_exists('mb_get_info'));
    \Thin\Autoloader::registerNamespace('ThinEntity',    APPLICATION_PATH . DS . 'entities');
    \Thin\Autoloader::registerNamespace('ThinHelper',    APPLICATION_PATH . DS . 'helpers');
    \Thin\Autoloader::registerNamespace('ThinModel',     APPLICATION_PATH . DS . 'models');
    \Thin\Autoloader::registerNamespace('ThinService',   APPLICATION_PATH . DS . 'services');
    \Thin\Autoloader::registerNamespace('ThinPlugin',    APPLICATION_PATH . DS . 'plugins');
    \Thin\Autoloader::registerNamespace('ThinForm',      APPLICATION_PATH . DS . 'forms');

    spl_autoload_register('Thin\\Autoloader::autoload');

    container();

    define('THINSTART', time());

    $protocol = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
        $protocol = 'https';
    }

    $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . "/";

    if (strstr($urlSite, '//')) {
        $urlSite = repl('//', '/', $urlSite);
        $urlSite = repl($protocol . ':/', $protocol . '://', $urlSite);
    }

    if (\Thin\Inflector::upper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $tab = explode('\\', $urlSite);
        $r = '';
        foreach ($tab as $c => $v) {
            $r .= $v;
        }
        $r = repl('//', '/', $r);
        $r = repl($protocol . ':/', $protocol . '://', $r);
        $urlSite = $r;
    }

    \Thin\Utils::set("urlsite", $urlSite);
    define('URLSITE', $urlSite);
