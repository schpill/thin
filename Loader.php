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

            if (!startsWith($message, 'Undefined offset:')) {
                showException($exception);
            }
        });

        register_shutdown_function(function() {
            $exception = error_get_last();

            if($exception) {
                showException($exception);
            }
        });
    }

    define('MB_STRING', (int) function_exists('mb_get_info'));

    spl_autoload_register('Thin\\Autoloader::autoload');

