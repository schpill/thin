<?php
    function ThinAutoload($className)
    {
        static $classes = array();
        $aliases = array(
            'u' => 'Thin\\Utils',
            'i' => 'Thin\\Inflector',
            'c' => 'Thin\\Container',
            'o' => 'Thin\\Object',
        );
        if (ake($className, $aliases)) {
            class_alias($aliases[$className], $className);
            $className = $aliases[$className];
        }
        $check = LIBRARIES_PATH . DS . preg_replace('#\\\|_(?!.+\\\)#', DS, $className) . '.php';
        if(file_exists($check) && !ake($className, $classes)) {
            require_once $check;
            $classes[$className] = true;
        } else {
            if (!ake($className, $classes)) {
                if (strstr($className, 'ResultModelCollection')) {
                    if (!class_exists($className)) {
                        $addLoadMethod = 'public function first() {return $this->cursor(1);} public function last() {return $this->cursor(count($this));} public function cursor($key) {$val = $key - 1; return $this[$val];} public function load(){$coll = $this->_args[0][0];$pk = $coll->pk();$objId = $coll->$pk;return $coll->find($objId);}';
                        eval("class $className extends \\Thin\\Object {public static function getNew() {return new self(func_get_args());}public static function getInstance() {return \\Thin\\Utils::getInstance($className, func_get_args());} public function getArg(\$key){if (isset(\$this->_args[0][\$key])) {return \$this->_args[0][\$key];} return null;}$addLoadMethod}");
                    }
                } elseif (substr($className, 0, strlen('Model_')) == 'Model_') {
                    if (!class_exists($className)) {
                        eval("class $className extends \\Thin\\Orm {public function __construct(\$id = null) { list(\$dummy, \$this->_entity, \$this->_table) = explode('_', strtolower(get_class(\$this)), 3); \$this->factory(); if (null === \$id) {\$this->foreign(); return \$this;} else {return \$this->find(\$id);}}}");
                    }
                } else {
                    class_alias('Thin\\Container', $className);
                }
                $classes[$className] = true;
            }
        }
    }

    require_once 'Helper.php';

    define('MB_STRING', (int) function_exists('mb_get_info'));

    spl_autoload_register('ThinAutoload');

    set_exception_handler(function($exception) {
        $router = plugin('router');
        u::set('ThinError', $exception);
        $router::isError();
        \Thin\Bootstrap::run();
    });


    set_error_handler(function($type, $message, $file, $line) {
        $exception = new \ErrorException($message, $type, 0, $file, $line);
        $router = plugin('router');
        u::set('ThinError', $exception);
        $router::isError();
        \Thin\Bootstrap::run();
    });


    $register_shutdown_function = function ($error) {
        extract($error, EXTR_SKIP);
        $exception = new \ErrorException($message, $type, 0, $file, $line);
        $router = plugin('router');
        u::set('ThinError', $exception);
        $router::isError();
        \Thin\Bootstrap::run();
    };

    \Thin\Event::set('register_shutdown_function', $register_shutdown_function);

    register_shutdown_function(function() {
        $error = error_get_last();
        if (null !== $error) {
            \Thin\Event::run('register_shutdown_function', array($error));
        }
    });

    error_reporting(-1);

    define('THINSTART', time());

    $protocol = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
        $protocol = 'https';
    }

    $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . dirname($_SERVER["SCRIPT_NAME"]) . "/";

    if (strstr($urlSite, '//')) {
        $urlSite = repl('//', '/', $urlSite);
        $urlSite = repl($protocol . ':/', $protocol . '://', $urlSite);
    }
    if (\i::upper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $tab = explode('\\', $urlSite);
        $r = '';
        foreach ($tab as $c => $v) {
            $r .= $v;
        }
        $r = repl('//', '/', $r);
        $r = repl($protocol . ':/', $protocol . '://', $r);
        $urlSite = $r;
    }
    u::set("urlsite", $urlSite);


    define('URLSITE', $urlSite);

    require_once APPLICATION_PATH . DS . 'Bootstrap.php';
    \Thin\Timer::start();

    \Thin\Bootstrap::init();

    /* stats */
    if (null !== u::get("showStats")) {
        \Thin\Timer::stop();
        $executionTime  = \Thin\Timer::get();
        $queries        = (null === u::get('NbQueries')) ? 0 : u::get('NbQueries');
        $valQueries     = ($queries < 2) ? 'requete SQL executee' : 'requetes SQL executees';
        $SQLDuration    = (null === u::get('SQLTotalDuration')) ? 0 : u::get('SQLTotalDuration');
        $execPHP        = $executionTime - $SQLDuration;
        $PCPhp          = round(($execPHP / $executionTime) * 100, 2);
        $PCSQL          = 100 - $PCPhp;
        echo "\n<!--\n\n\tPage generee en $executionTime s.\n\t$queries $valQueries en $SQLDuration s. (" . ($PCSQL) . " %)\n\tExecution PHP $execPHP s. ($PCPhp %)\n\n\tMemoire utilisee : " . convertSize(memory_get_peak_usage()) . "\n\n-->";
    }
