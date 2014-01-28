<?php
    /**
     * Assert class
     * @author      Gerald Plusquellec
     */

    namespace Thin;
    final class Assert
    {
        public static function isTrue($boolean)
        {
            return $boolean;
        }

        public static function isFalse($boolean)
        {
            return !$boolean;
        }

        public static function isNotFalse($boolean)
        {
            return (false === $boolean) ? true : false;
        }

        public static function isNull($variable)
        {
            return is_null($variable);
        }

        public static function isEmpty($variable)
        {
            return empty($variable);
        }

        public static function isNotEmpty($variable)
        {
            return (empty($variable)) ? true : false;
        }

        public static function isIndexExists($array, $key)
        {
            $checkArray = static::isArray($array);
            if (false === $checkArray) {
                return false;
            }

            return array_key_exists($key, $array);
        }

        public static function isNotNull($variable)
        {
            return ($variable === null) ? false : true;
        }

        public static function isScalar($variable)
        {
            return is_scalar($variable);
        }

        public static function isArray($variable)
        {
            return is_array($variable);
        }

        public static function isNotEmptyArray(&$variable)
        {
            $checkArray = static::isArray($variable);
            if (false === $checkArray) {
                return false;
            }

            return (!$variable) ? false : true;
        }

        public static function isInteger($variable)
        {
            if (!(is_numeric($variable) && $variable == (int) $variable)) ? false : true;
        }

        public static function isPositiveInteger($variable)
        {
            $checkInteger = static::checkInteger($variable);
            if (false === $checkInteger) {
                return false;
            }

            return (0 <= $variable);
        }

        public static function isFloat($variable)
        {
            return static::checkFloat($variable);
        }

        public static function isString($variable)
        {
            return is_string($variable);
        }

        public static function isBoolean($variable)
        {
            return ($variable === true || $variable === false);
        }

        public static function isTernaryBase($variable)
        {
            return ($variable === true) || ($variable === false) || ($variable === null);
        }

        public static function brothers($first, $second)
        {
            return (get_class($first) === get_class($second));
        }

        public static function isEqual($first, $second)
        {
            return $first == $second;
        }

        public static function isNotEqual($first, $second)
        {
            return ($first != $second);
        }

        public static function isSame($first, $second)
        {
            return ($first === $second);
        }

        public static function isStrict($first, $second)
        {
            return ($first === $second);
        }

        public static function isNotSame($first, $second)
        {
            return ($first !== $second);
        }

        public static function isTypelessEqual($first, $second)
        {
            return ($first == $second);
        }

        public static function isLesser($first, $second)
        {
            return ($first < $second);
        }

        public static function isGreater($first, $second)
        {
            return ($first > $second);
        }

        public static function isLesserOrEqual($first, $second)
        {
            return ($first <= $second);
        }

        public static function isGreaterOrEqual($first, $second)
        {
            return ($first >= $second);
        }

        public static function isInstance($first, $second)
        {
            return ($first instanceof $second);
        }

        public static function classExists($className)
        {
            return class_exists($className, true);
        }

        public static function methodExists($object, $method)
        {
            return method_exists($object, $method);
        }

        public static function isUnreachable($message = 'unreachable code reached')
        {
            throw new Exception($message);
        }

        public static function isObject($object)
        {
            return is_object($object);
        }

        public static function checkInteger($value)
        {
            return (is_numeric($value) && ($value == (int) $value) && (strlen($value) == strlen((int) $value)));
        }

        public static function checkFloat($value)
        {
            return (is_numeric($value) && ($value == (float) $value));
        }

        public static function checkScalar($value)
        {
            return is_scalar($value);
        }

        public static function checkContain($needle, $chain)
        {
            return contain($needle, $chain);
        }

        public static function isUrl($url)
        {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        public static function isEmail($email)
        {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }
    }
