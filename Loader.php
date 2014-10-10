<?php
    clearstatcache();

    require_once 'Helper.php';
    require_once realpath(APPLICATION_PATH . DS . '..' . DS . 'vendor') . DS . 'autoload.php';


    if (true === $debug) {
        error_reporting(-1);

        set_exception_handler(function($exception) {

            dd($exception);
        });

        set_error_handler(function($type, $message, $file, $line) {
            $exception = new ErrorException($message, $type, 0, $file, $line);

            if (!startsWith($message, 'Undefined offset:')) {
                dd($exception);
            }
        });

        register_shutdown_function(function() {
            $exception = error_get_last();

            if($exception) {
                dd($exception);
            }
        });
    }

    // spl_autoload_register('Thin\\Autoloader::autoload');

