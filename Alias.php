<?php
    namespace Thin;

    class Alias
    {
        public static function facade($to, $target, $namespace = 'Thin')
        {
            $class  = '\\' . $namespace . '\\' . $target;
            $to     = '\\Thin\\' . $to;

            if (class_exists($class) && !class_exists($to)) {
                class_alias($class, $to);
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
    }
