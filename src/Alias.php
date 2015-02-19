<?php
    namespace Thin;

    use Closure;

    class Alias
    {
        protected static $cb = [];

        public static function facade($to, $target, $namespace = 'Thin')
        {
            $class  = '\\' . $namespace . '\\' . $target;
            $target = $to;
            $to     = '\\Thin\\' . $to;

            if (class_exists($class) && !class_exists($to)) {
                eval('namespace Thin; class ' . $target . ' extends ' . $class . ' {}');
            } else {
                if (!class_exists($class)) {
                    throw new Exception("The class '$class' does not exist.");
                } elseif (class_exists($to)) {
                    throw new Exception("The class '$to' ever exists and cannot be aliased.");
                } else {
                    throw new Exception("A problem occured.");
                }
            }
        }

        public static function capsule($to, $target, $namespace = 'Thin')
        {
            $class  = '\\' . $namespace . '\\' . $target;
            $toNS   = '\\Thin\\' . $to;

            if (class_exists($class) && !class_exists($toNS)) {
                eval('namespace Thin; class ' . $to . ' extends ' . $target . ' {}');
            } else {
                if (!class_exists($class)) {
                    throw new Exception("The class '$class' does not exist.");
                } elseif (class_exists($to)) {
                    throw new Exception("The class '$to' ever exists and cannot be capsulated.");
                } else {
                    throw new Exception("A problem occured.");
                }
            }
        }

        public static function callback($class, $method, Closure $cb)
        {
            $callbackClass = ucfirst(Inflector::lower($class));

            if (!class_exists('Thin\\' . $callbackClass)) {
                eval("namespace Thin; class $callbackClass extends Alias {}");
            }

            static::$cb[$callbackClass][$method] = $cb;
        }

        public static function __callStatic($method, $args)
        {
            $calledClass = str_replace('Thin\\', '', get_called_class());

            $cbs    = isAke(Alias::$cb, $calledClass, []);
            $cb     = isAke($cbs, $method, false);

            if (false !== $cb) {
                if (is_callable($cb)) {
                    return call_user_func_array($cb, $args);
                }
            }
        }
    }
