<?php
    namespace Thin;

    class Autoloader
    {
        private static $_paths = array();
        private static $_classes = array();
        public static $calls = 0;

        public static function registerNamespace($ns, $path)
        {
            if (!array_key_exists($ns, static::$_paths)) {
                static::$_paths[$ns] = $path;
            }
        }

        public static function registerNamespaces(array $namespaces)
        {
            if (count($namespaces)) {
                foreach ($namespaces as $ns => $path) {
                    static::registerNamespace($ns, $path);
                }
            }
        }

        public static function autoload($className)
        {
            if (strstr(strtolower($className), 'laravel')) {
                $className = strtolower($className);
            }
            if (strstr(strtolower($className), 'predis')) {
                $className2 = str_replace('Predis\\', '', $className);
                $className2 = str_replace('Predis', '', $className2);
                $check = LIBRARIES_PATH . DS . 'predis' . DS . 'lib' . DS . 'Predis' . DS . preg_replace('#\\\|_(?!.+\\\)#', DS, $className2) . '.php';
                $parts = explode('\\', substr($className, strlen('Predis\\')));
                $filepath = LIBRARIES_PATH . DS . 'predis' . DS . 'lib' . DS . 'Predis' . DS . implode(DS, $parts) . '.php';
                // var_dump($filepath);
                if(is_readable($filepath) && !array_key_exists($className, static::$_classes)) {
                    static::$calls++;
                    require_once($filepath);
                    static::$_classes[$className] = true;
                }
            }
            $aliases = array(
                'c' => 'Thin\\Container',
                'o' => 'Thin\\Object',
                'u' => 'Thin\\Utils',
                'i' => 'Thin\\Inflector',
            );
            if (array_key_exists($className, $aliases)) {
                class_alias($aliases[$className], $className);
                $className = $aliases[$className];
            }

            $ns = ucfirst(strtolower(SITE_NAME));
            if (strstr($className, $ns . '\\')) {
                $file = realpath(
                    APPLICATION_PATH
                ) . DS . SITE_NAME . DS . 'app' . DS . 'lib' . DS .
                str_replace(
                    '\\',
                    DS,
                    str_replace(
                        $ns . '\\',
                        '',
                        $className
                    )
                ) . '.php';

                if(is_readable($file) && !array_key_exists($className, static::$_classes)) {
                    static::$calls++;
                    require_once($file);
                    static::$_classes[$className] = true;
                }
            }


            $check = LIBRARIES_PATH . DS . preg_replace('#\\\|_(?!.+\\\)#', DS, $className) . '.php';

            if(is_readable($check) && !array_key_exists($className, static::$_classes)) {
                static::$calls++;
                require_once $check;
                $classes[$className] = true;
            } else {
                $tab = explode(DS, $check);
                $last = end($tab);
                $check = str_replace(DS . $last, DS . strtolower($last), $check);
                if(is_readable($check) && !array_key_exists($className, static::$_classes)) {
                    static::$calls++;
                    require_once $check;
                    $classes[$className] = true;
                } else {
                    if (!array_key_exists($className, static::$_classes)) {
                        if (strstr($className, 'Model_')) {
                            eval("class $className extends Thin\\Orm {public function __construct(\$id = null) { list(\$this->_entity, \$this->_table) = explode('_', str_replace('model_', '', strtolower(get_class(\$this))), 2); \$this->factory(); if (null === \$id) {\$this->foreign(); return \$this;} else {return \$this->find(\$id);}}}");
                            static::$_classes[$className] = true;
                        }
                        if (strstr($className, 'ResultModelCollection')) {
                            if (!class_exists($className)) {
                                $addLoadMethod = 'public function first() {return $this->cursor(1);} public function last() {return $this->cursor(count($this));} public function cursor($key) {$val = $key - 1; return $this[$val];} public function load(){$coll = $this->_args[0][0];$pk = $coll->pk();$objId = $coll->$pk;return $coll->find($objId);}';
                                eval("class $className extends Thin\\Object {public static function getNew() {return new self(func_get_args());}public static function getInstance() {return \\Thin\\Utils::getInstance($className, func_get_args());} public function getArg(\$key){if (isset(\$this->_args[0][\$key])) {return \$this->_args[0][\$key];} return null;}$addLoadMethod}");
                                static::$_classes[$className] = true;
                            }
                        }
                        foreach (static::$_paths as $ns => $path) {
                            $file = $path . preg_replace('#\\\|_(?!.+\\\)#', DS, str_replace($ns, '', $className)) . '.php';
                            if(is_readable($file)) {
                                static::$calls++;
                                require_once $file;
                                static::$_classes[$className] = true;
                            }
                        }
                        if (!array_key_exists($className, static::$_classes)) {
                            $check = LIBRARIES_PATH . DS . preg_replace('#\\\|_(?!.+\\\)#', DS, 'Thin\\' . $className) . '.php';
                            if(is_readable($check) && !array_key_exists($className, static::$_classes)) {
                                static::$calls++;
                                require_once $check;
                                $classes[$className] = true;
                            }
                        }
                        if (!array_key_exists($className, static::$_classes) && !strstr($className, 'this_')) {
                            class_alias('Thin\\Container', $className);
                            static::$_classes[$className] = true;
                        }
                    }
                }
            }
        }
    }

