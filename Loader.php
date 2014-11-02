<?php
    clearstatcache();

    require_once 'Helper.php';
    require_once realpath(APPLICATION_PATH . DS . '..' . DS . 'vendor') . DS . 'autoload.php';

    $debug = 'production' != APPLICATION_ENV;

    if (true === $debug) {
        error_reporting(-1);

        set_exception_handler(function($exception) {
            vd('EXCEPTION', $exception, debug_backtrace());
        });

        set_error_handler(function($type, $message, $file, $line) {
            $exception = new \ErrorException($message, $type, 0, $file, $line);

            if (!fnmatch('Undefined offset:*', $message)) {
                dd('ERROR', $exception, debug_backtrace());
            }
        });

        register_shutdown_function(function() {
            $exception = error_get_last();

            if ($exception) {
                $message = isAke($exception, 'message', 'NA');

                if (!fnmatch('*undefinedVariable*', $message)) {
                    dd('ERROR', $exception, debug_backtrace());
                }
            }
        });
    }

    spl_autoload_register('Thin\\Autoloader::autoload');

