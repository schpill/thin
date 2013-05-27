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

    set_exception_handler(function($e) {
        var_dump($e);
    });


    set_error_handler(function($code, $error, $file, $line) {
        var_dump($code, $error, $file, $line);
    });


    $register_shutdown_function = function ($error) {
        extract($error, EXTR_SKIP);
        $exception = new \ErrorException($message, $type, 0, $file, $line);
        dieDump($error);
    };

    \Thin\Event::set('register_shutdown_function', $register_shutdown_function);

    register_shutdown_function(function() {
        $error = error_get_last();
        if (null !== $error) {
            \Thin\Event::run('register_shutdown_function', array($error));
        }
    });

    error_reporting(-1);

    require_once APPLICATION_PATH . DS . 'Bootstrap.php';
    \Thin\Timer::start();

    \Thin\Bootstrap::init();

    /* stats */
    if (null !== u::get("showStats")) {
        \Thin\Timer::stop();
        $executionTime  = \Thin\Timer::get();
        $queries        = u::get('NbQueries');
        $SQLDuration    = u::get('SQLTotalDuration');
        $execPHP        = $executionTime - $SQLDuration;
        $PCPhp          = round(($execPHP / $executionTime) * 100, 2);
        $PCSQL          = 100 - $PCPhp;
        echo "\n<!--\n\n\tPage générée en $executionTime s.\n\t$queries requêtes SQL exécutées en $SQLDuration s. (" . ($PCSQL) . " %)\n\tExécution PHP $execPHP s. ($PCPhp %)\n\n\tMémoire utilisée : " . convertSize(memory_get_peak_usage()) . "\n\n-->";
    }
