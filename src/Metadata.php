<?php
    namespace Thin;
    class Metadata
    {
        public function __call($func, $argv)
        {
            if (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $key = Inflector::lower($uncamelizeMethod);
                return getMeta($key);
            } elseif (substr($func, 0, 3) == 'set') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $key    = Inflector::lower($uncamelizeMethod);
                $value  = Arrays::first($argv);
                setMeta($key, $value);
                return $this;
            } elseif (substr($func, 0, 3) == 'has') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $key = Inflector::lower($uncamelizeMethod);
                return null !== getMeta($key);
            }
        }
    }
