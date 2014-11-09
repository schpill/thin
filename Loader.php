<?php
    clearstatcache();

    require_once 'Helper.php';
    require_once realpath(APPLICATION_PATH . DS . '..' . DS . 'vendor') . DS . 'autoload.php';

    $debug = 'production' != APPLICATION_ENV;

    if (true === $debug) {
        error_reporting(-1);

        set_exception_handler(function($exception) {
            vd('EXCEPTION', $exception, $GLOBALS['dbg_stack']);
        });

        set_error_handler(function($type, $message, $file, $line) {
            $exception      = new ErrorException($message, $type, 0, $file, $line);

            $typeError      = Thin\Arrays::in(
                $type,
                [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]
            ) ? 'FATAL ERROR' : 'ERROR';

            if (!fnmatch('Undefined offset:*', $message) && !fnmatch('*StreamConnection.php*', $file)) {
                $start      = $line > 5 ? $line - 5 : $line;
                $code       = Thin\File::readLines($file, $start, $line + 5);

                $lines      = explode("\n", $code);

                $codeLines  = [];

                $i          = $start;

                foreach ($lines as $codeLine) {
                    if ($i == $line) {
                        array_push($codeLines, $i . '. <span style="background-color: gold; color: black;">' . $codeLine . '</span>');
                    } else {
                        array_push($codeLines, $i . '. ' . $codeLine);
                    }

                    $i++;
                }

                dd(
                    '<div style="text-align: center; padding: 5px; color: black; border: solid 1px black; background: #f2f2f2;">' . $typeError . '</div>',
                    '<div style="padding: 5px; color: red; border: solid 1px red; background: #f2f2f2;">' . $message . '</div>',
                    '<div style="padding: 5px; color: navy; border: solid 1px navy; background: #f2f2f2;">' . $file . ' [<em>line: <u>' . $line . '</u></em>]</div>',
                    '<div style="font-family: Consolas; font-weight: 400; padding: 5px; color: green; border: solid 1px green; background: #f2f2f2;">' . implode("\n", $codeLines) . '</div>',
                    '<div style="text-align: center; padding: 5px; color: black; border: solid 1px black; background: #f2f2f2;">BACKTRACE</div>',
                    '<div style="padding: 5px; color: purple; border: solid 1px purple; background: #f2f2f2;">' . displayCodeLines() . '</div>'
                );
            }
        });

        register_shutdown_function(function() {
            $exception = error_get_last();

            if ($exception) {
                $message    = isAke($exception, 'message', 'NA');
                $type       = isAke($exception, 'type', 1);
                $line       = isAke($exception, 'line', 1);
                $file       = isAke($exception, 'file');
                $exception  = new ErrorException($message, $type, 0, $file, $line);

                $typeError  = Thin\Arrays::in(
                    $type,
                    [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]
                ) ? 'FATAL ERROR' : 'ERROR';

                if (!fnmatch('*undefinedVariable*', $message) && file_exists($file)) {
                    $start      = $line > 5 ? $line - 5 : $line;
                    $code       = Thin\File::readLines($file, $start, $line + 5);

                    $lines      = explode("\n", $code);

                    $codeLines  = [];

                    $i          = $start;

                    foreach ($lines as $codeLine) {
                        if ($i == $line) {
                            array_push($codeLines, $i . '. <span style="background-color: gold; color: black;">' . $codeLine . '</span>');
                        } else {
                            array_push($codeLines, $i . '. ' . $codeLine);
                        }

                        $i++;
                    }

                    dd(
                        '<div style="text-align: center; padding: 5px; color: black; border: solid 1px black; background: #f2f2f2;">' . $typeError . '</div>',
                        '<div style="padding: 5px; color: red; border: solid 1px red; background: #f2f2f2;">' . $message . '</div>',
                        '<div style="padding: 5px; color: navy; border: solid 1px navy; background: #f2f2f2;">' . $file . ' [<em>line: <u>' . $line . '</u></em>]</div>',
                        '<div style="font-family: Consolas; font-weight: 400; padding: 5px; color: green; border: solid 1px green; background: #f2f2f2;">' . implode("\n", $codeLines) . '</div>',
                        '<div style="text-align: center; padding: 5px; color: black; border: solid 1px black; background: #f2f2f2;">BACKTRACE</div>',
                        '<div style="padding: 5px; color: purple; border: solid 1px purple; background: #f2f2f2;">' . displayCodeLines() . '</div>'
                    );
                }
            }
        });
    }

    register_tick_function(function() {
        static $m = 0;

        $mNow = memory_get_usage();

        if ($mNow > $m) {
            $GLOBALS['max_memory'] = $mNow;
        }

        $backtrace  = debug_backtrace();
        $line       = $backtrace[0]['line'] - 1;
        $file       = $backtrace[0]['file'];

        if ($file == __FILE__) return;

        $GLOBALS['dbg_stack'] = $backtrace;
    });

    declare(ticks = 1);

    spl_autoload_register('Thin\\Autoloader::autoload');

    function displayCodeLines()
    {
        $back   = '';

        $traces = Thin\Input::globals('dbg_stack', []);

        if (count($traces)) {
            foreach($traces as $trace) {
                $file = isAke($trace, 'file', false);
                $line = isAke($trace, 'line', false);

                if (false !== $file && false !== $line) {
                    $start      = $line > 5 ? $line - 5 : $line;
                    $code       = Thin\File::readLines($file, $start, $line + 5);

                    $lines      = explode("\n", $code);

                    $codeLines  = [];

                    $i          = $start;

                    foreach ($lines as $codeLine) {
                        if ($i == $line) {
                            array_push($codeLines, $i . '. <span style="background-color: gold; color: black;">' . $codeLine . '</span>');
                        } else {
                            array_push($codeLines, $i . '. ' . $codeLine);
                        }

                        $i++;
                    }

                    if (strlen($back)) {
                        $back .= "\n";
                    }

                    $back .= "File: $file [<em>line: <u>$line</u></em>]\n\nCode\n*******************************\n<div style=\"font-weight: normal; font-family: Consolas;\">" . implode("\n", $codeLines) . "</div>\n*******************************\n";
                }
            }
        }

        return $back;
    }

