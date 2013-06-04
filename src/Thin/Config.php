<?php
    namespace Thin;
    class Config
    {
        public static function get($key, $default = null)
        {
            return Utils::get($key);
        }

        public static function set($key, $value = null)
        {
            return Utils::set($key, $value);
        }

        public static function has($key)
        {
            return !is_null(static::get($key));
        }

        public static function loadIni($conf, $environment = true)
        {
            $file = APPLICATION_PATH . DS . 'config' . DS . Inflector::lower($conf) . '.ini';
            if (file_exists($file)) {
                if (true === $environment) {
                    $config = new \Zend_Config_Ini($file, APPLICATION_ENV);
                } else {
                    $config = new \Zend_Config_Ini($file);
                }
                $config = $config->toArray();
                static::setArray($conf, $config);
            } else {
                throw new Exception("The config file '$conf' does not exist.");
            }
        }

        public static function load($conf, $environment = true)
        {
            if (null === static::get($conf)) {
                $file = APPLICATION_PATH . DS . 'config' . DS . $conf . '.php';
                if (file_exists($file)) {
                    $config = include($file);
                    if (is_array($config)) {
                        if (true === $environment) {
                            $configMerge = $config['production'];
                            if (ake(APPLICATION_ENV, $config)) {
                                $configMerge = static::merge($configMerge, $config[APPLICATION_ENV]);
                            }
                            $config = $configMerge;
                        }
                        static::setArray($conf, $config);
                    }
                } else {
                    throw new Exception("The config file '$conf' does not exist.");
                }
            }
        }

        private static function setArray($conf, $array)
        {
            foreach ($array as $k => $v) {
                if (!is_array($v)) {
                    static::set($conf . '.' . $k, $v);
                } else {
                    static::setArray($conf . '.' . $k, $v);
                }
            }
        }

        public static function defined($var = null)
        {
            $defined = new Defined;
            $defines = get_defined_constants();
            $defined->populate($defines, 'defined');
            if (null !== $var) {
                return (true === ake($var, $defines)) ? $defines[$var] : null;
            }
            return $defined;
        }

        public static function env()
        {
            return APPLICATION_ENV;
        }

        public static function __callStatic($method, $args)
        {
            if (substr($method, 0, 3) == 'set') {
                $uncamelizeMethod = \i::uncamelize(lcfirst(substr($method, 3)));
                $var = repl('_', '.', \i::lower($uncamelizeMethod));
                return static::set($var, $args[0]);
            } elseif (substr($method, 0, 3) == 'get') {
                $uncamelizeMethod = \i::uncamelize(lcfirst(substr($method, 3)));
                $var = repl('_', '.', \i::lower($uncamelizeMethod));
                return static::get($var);
            }
        }

        private static function merge($ref, $new)
        {
            $args = func_get_args();
            $res = array_shift($args);
            while(!empty($args)) {
                $next = array_shift($args);
                foreach($next as $k => $v) {
                    if(is_integer($k)) {
                        isset($res[$k]) ? $res[] = $v : $res[$k]=  $v;
                    } elseif(is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                        $res[$k] = static::merge($res[$k], $v);
                    } else {
                        $res[$k] = $v;
                    }
                }
            }
            return $res;
        }
    }
