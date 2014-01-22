<?php
    namespace Thin;

    class Autoloader
    {
        private static $_paths = array();
        private static $_classes = array();

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
            $aliases = array(
                'u' => 'Thin\\Utils',
                'i' => 'Thin\\Inflector',
                'c' => 'Thin\\Container',
                'o' => 'Thin\\Object',
            );
            if (array_key_exists($className, $aliases)) {
                class_alias($aliases[$className], $className);
                $className = $aliases[$className];
            }
            $check = LIBRARIES_PATH . DS . preg_replace('#\\\|_(?!.+\\\)#', DS, $className) . '.php';
            if(is_readable($check) && !array_key_exists($className, static::$_classes)) {
                require_once $check;
                $classes[$className] = true;
            } else {
                if (!array_key_exists($className, static::$_classes)) {
                    if (strstr($className, 'ResultModelCollection')) {
                        if (!class_exists($className)) {
                            $addLoadMethod = 'public function first() {return $this->cursor(1);} public function last() {return $this->cursor(count($this));} public function cursor($key) {$val = $key - 1; return $this[$val];} public function load(){$coll = $this->_args[0][0];$pk = $coll->pk();$objId = $coll->$pk;return $coll->find($objId);}';
                            eval("class $className extends \\Thin\\Object {public static function getNew() {return new self(func_get_args());}public static function getInstance() {return \\Thin\\Utils::getInstance($className, func_get_args());} public function getArg(\$key){if (isset(\$this->_args[0][\$key])) {return \$this->_args[0][\$key];} return null;}$addLoadMethod}");
                        }
                    }
                    foreach (static::$_paths as $ns => $path) {
                        $file = $path . repl('\\', DS, repl($ns, '', $className)) . '.php';
                        if(is_readable($file)) {
                            require_once $file;
                            static::$_classes[$className] = true;
                        }
                    }
                    if (!array_key_exists($className, static::$_classes)) {
                        class_alias('Thin\\Container', $className);
                        static::$_classes[$className] = true;
                    }
                }
            }
        }
    }

