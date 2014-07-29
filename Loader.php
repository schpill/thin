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
                vd($error);
            }
        });
    }

    require_once 'Helper.php';
    require_once 'Autoloader.php';
    require_once 'Swift/swift_required.php';
    require_once 'facebook/facebook.php';

    define('MB_STRING', (int) function_exists('mb_get_info'));
    // Thin\Autoloader::registerNamespace('ThinEntity',    APPLICATION_PATH . DS . 'entities');
    // Thin\Autoloader::registerNamespace('ThinHelper',    APPLICATION_PATH . DS . 'helpers');
    // Thin\Autoloader::registerNamespace('ThinModel',     APPLICATION_PATH . DS . 'models');
    // Thin\Autoloader::registerNamespace('ThinService',   APPLICATION_PATH . DS . 'services');
    // Thin\Autoloader::registerNamespace('ThinPlugin',    APPLICATION_PATH . DS . 'plugins');
    // Thin\Autoloader::registerNamespace('ThinForm',      APPLICATION_PATH . DS . 'forms');
    // Thin\Autoloader::registerNamespace('ThinProject',   APPLICATION_PATH . DS . 'lib');

    spl_autoload_register('Thin\\Autoloader::autoload');

