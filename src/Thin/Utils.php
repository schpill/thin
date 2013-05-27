<?php
    namespace Thin;
    class Utils
    {
        protected static $vars = array();
        protected static $_instances = array();

        public static function get($key, $default = null)
        {
            return ake($key, static::$vars) ? static::$vars[$key] : $default;
        }

        public static function set($key, $value = null)
        {
            if (is_array($key) || is_object($key)) {
                foreach ($key as $k => $v) {
                    static::$vars[$k] = $v;
                }
            } else {
                static::$vars[$key] = $value;
            }
        }

        public static function has($key)
        {
            return ake($key, static::$vars);
        }

        public static function clear($key = null)
        {
            if (null === $key) {
                static::$vars = array();
            } else {
                if (ake($key, static::$vars)) {
                    unset(static::$vars[$key]);
                }
            }
        }

        public static function getvars()
        {
            return static::$vars;
        }

        public static function __callstatic($method, $args)
        {
            if (substr($method, 0, 3) == 'get') {
                $uncamelizeMethod = \i::uncamelize(lcfirst(substr($method, 3)));
                $var = \i::lower($uncamelizeMethod);
                $return = static::get($var);
                return $return;
            } elseif (substr($method, 0, 3) == 'set') {
                $uncamelizeMethod = \i::uncamelize(lcfirst(substr($method, 3)));
                $var = \i::lower($uncamelizeMethod);
                $value = current($args);
                return static::set($var, $value);
            }
                }

        public static function toArray($object)
        {
            if (!is_object($object)) {
                throw new FTV_Exception("The param sent is not an object.");
            }
            $array = array();
            foreach ($object as $key => $value) {
                if (is_object($value)) {
                    $array[$key] = self::toArray($value);
                } else {
                    $array[$key] = $value;
                }
            }
            return $array;
        }

        public static function textBetweenTag($string, $tag = 'h1')
        {
            return self::cut("<$tag>", "</$tag>", $string);
        }

        public static function UUID()
        {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }

        public static function value($value)
        {
            return (is_callable($value) && !is_string($value)) ? call_user_func($value) : $value;
        }

        public static function getInstance($class, array $params = array())
        {
            if (!ake($class, static::$_instances)) {
                self::$_instances[$class] = static::newInstance($class, $params);
            }

            return static::$_instances[$class];
        }

        public static function newInstance($class, array $params = array())
        {
            /* until 5 params, it's faster to instantiate the class without reflection */
            switch (count($params)) {
                case 0:
                    return new $class();
                case 1:
                    return new $class($params[0]);
                case 2:
                    return new $class($params[0], $params[1]);
                case 3:
                    return new $class($params[0], $params[1], $params[2]);
                case 4:
                    return new $class($params[0], $params[1], $params[2], $params[3]);
                case 5:
                    return new $class($params[0], $params[1], $params[2], $params[3], $params[4]);
                default:
                    $refClass = new \ReflectionClass($class);
                    return $refClass->newInstanceArgs($params);
            }
        }

        public static function cleanCache()
        {
            $cacheFiles = glob(CACHE_PATH . DS . '*');
            $minToKeep = time() - 12 * 3600;
            foreach ($cacheFiles as $cacheFile) {
                $age = File::modified($cacheFile);
                if ($age < $minToKeep) {
                    $tabFile = explode(DS, $cacheFile);
                    ThinLog(end($tabFile) . ' => ' . date('d/m/Y H:i:s', $age), null, 'suppression cache');
                    File::delete($cacheFile);
                }
            }
        }
    }
