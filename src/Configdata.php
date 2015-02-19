<?php
    namespace Thin;
    class Configdata
    {
        public function __call($func, $argv)
        {
            if (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $key = Inflector::lower($uncamelizeMethod);
                if (isset($argv[0])) {
                    $environment = $argv[0];
                } else {
                    $environment = APPLICATION_ENV;
                }
                return getConfig($key, $environment);
            } elseif (substr($func, 0, 3) == 'set') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $key    = Inflector::lower($uncamelizeMethod);
                $value  = Arrays::first($argv);
                if (isset($argv[1])) {
                    $environment = $argv[1];
                } else {
                    $environment = 'all';
                }
                setConfig($key, $value, $environment);
                return $this;
            } elseif (substr($func, 0, 3) == 'has') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $key = Inflector::lower($uncamelizeMethod);
                if (isset($argv[0])) {
                    $environment = $argv[0];
                } else {
                    $environment = APPLICATION_ENV;
                }
                return null !== getConfig($key, $environment);
            }
        }
    }
