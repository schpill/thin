<?php
    clearstatcache();

    require_once 'Helper.php';
    require_once 'Autoloader.php';
    // require_once 'Swift/swift_required.php';
    // require_once 'facebook/facebook.php';

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

            showException($error);
        });
    }

    define('MB_STRING', (int) function_exists('mb_get_info'));
    // Thin\Autoloader::registerNamespace('ThinEntity',    APPLICATION_PATH . DS . 'entities');

    spl_autoload_register('Thin\\Autoloader::autoload');

