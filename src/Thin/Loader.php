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
                } elseif (substr($className, 0, strlen('ThinService')) == 'ThinService') {
                    $file = APPLICATION_PATH . DS . 'services' . repl('\\', DS, repl('ThinService', '', $className)) . '.php';
                    if(file_exists($file) && !ake($className, $classes)) {
                        require_once $file;
                        $classes[$className] = true;
                    } else {
                        throw new \Thin\Exception("The class $className [$file] does not exist.");
                    }
                } elseif (substr($className, 0, strlen('ThinModel')) == 'ThinModel') {
                    $file = APPLICATION_PATH . DS . 'models' . repl('\\', DS, repl('ThinModel', '', $className)) . '.php';
                    if(file_exists($file) && !ake($className, $classes)) {
                        require_once $file;
                        $classes[$className] = true;
                    } else {
                        throw new \Thin\Exception("The class $className [$file] does not exist.");
                    }
                } elseif (substr($className, 0, strlen('ThinHelper')) == 'ThinHelper') {
                    $file = APPLICATION_PATH . DS . 'helpers' . repl('\\', DS, repl('ThinHelper', '', $className)) . '.php';
                    if(file_exists($file) && !ake($className, $classes)) {
                        require_once $file;
                        $classes[$className] = true;
                    } else {
                        throw new \Thin\Exception("The class $className [$file] does not exist.");
                    }
                } elseif (substr($className, 0, strlen('ThinEntity')) == 'ThinEntity') {
                    $file = APPLICATION_PATH . DS . 'entities' . repl('\\', DS, repl('ThinEntity', '', $className)) . '.php';
                    if(file_exists($file) && !ake($className, $classes)) {
                        require_once $file;
                        $classes[$className] = true;
                    } else {
                        throw new \Thin\Exception("The class $className [$file] does not exist.");
                    }
                } elseif (substr($className, 0, strlen('ThinPlugin')) == 'ThinPlugin') {
                    $file = APPLICATION_PATH . DS . 'plugins' . repl('\\', DS, repl('ThinPlugin', '', $className)) . '.php';
                    if(file_exists($file) && !ake($className, $classes)) {
                        require_once $file;
                        $classes[$className] = true;
                    } else {
                        throw new \Thin\Exception("The class $className [$file] does not exist.");
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

    error_reporting(-1);

    //*GP* set_exception_handler(function($exception) {
        //*GP* var_dump($exception);
    //*GP* });
    //*GP* set_error_handler(function($type, $message, $file, $line) {
        //*GP* $exception = new \ErrorException($message, $type, 0, $file, $line);
        //*GP* var_dump($exception);
    //*GP* });
    //*GP* register_shutdown_function(function() {
        //*GP* $error = error_get_last();
        //*GP* var_dump($error);
    //*GP* });

    require_once 'Helper.php';

    define('MB_STRING', (int) function_exists('mb_get_info'));

    spl_autoload_register('ThinAutoload');

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
