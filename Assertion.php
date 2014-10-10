<?php

    namespace Thin;
    class Assertion
    {
        const INVALID_FLOAT             = 9;
        const INVALID_INTEGER           = 10;
        const INVALID_DIGIT             = 11;
        const INVALID_INTEGERISH        = 12;
        const INVALID_BOOLEAN           = 13;
        const VALUE_EMPTY               = 14;
        const VALUE_NULL                = 15;
        const INVALID_STRING            = 16;
        const INVALID_REGEX             = 17;
        const INVALID_MIN_LENGTH        = 18;
        const INVALID_MAX_LENGTH        = 19;
        const INVALID_STRING_START      = 20;
        const INVALID_STRING_CONTAINS   = 21;
        const INVALID_CHOICE            = 22;
        const INVALID_NUMERIC           = 23;
        const INVALID_ARRAY             = 24;
        const INVALID_KEY_EXISTS        = 26;
        const INVALID_NOT_BLANK         = 27;
        const INVALID_INSTANCE_OF       = 28;
        const INVALID_SUBCLASS_OF       = 29;
        const INVALID_RANGE             = 30;
        const INVALID_ALNUM             = 31;
        const INVALID_TRUE              = 32;
        const INVALID_EQ                = 33;
        const INVALID_SAME              = 34;
        const INVALID_MIN               = 35;
        const INVALID_MAX               = 36;
        const INVALID_LENGTH            = 37;
        const INVALID_FALSE             = 38;
        const INVALID_STRING_END        = 39;
        const INVALID_UUID              = 40;
        const INVALID_COUNT             = 41;
        const INVALID_NOT_EQ            = 42;
        const INVALID_NOT_SAME          = 43;
        const INVALID_DIRECTORY         = 101;
        const INVALID_FILE              = 102;
        const INVALID_READABLE          = 103;
        const INVALID_WRITEABLE         = 104;
        const INVALID_CLASS             = 105;
        const INVALID_EMAIL             = 201;
        const INTERFACE_NOT_IMPLEMENTED = 202;
        const INVALID_URL               = 203;
        const INVALID_NOT_INSTANCE_OF   = 204;
        const VALUE_NOT_EMPTY           = 205;
        const INVALID_JSON_STRING       = 206;

        /**
         * Helper method that handles building the assertion failure exceptions.
         * They are returned from this method so that the stack trace still shows
         * the assertions method.
         */
        static protected function createException($value, $message, $code, $propertyPath, array $constraints = array())
        {
            $constraintString = 'Constraints: ';

            if (count($constraints)) {
                foreach ($constraints as $key => $v) {
                    $constraintString .= "$key => $v, ";
                }

                $constraintString = substr($$constraintString, 0, -2);
            }

            return new Exception("$message => $code => $value => $propertyPath => $constraintString");
        }

        /**
         * Assert that two values are equal (using == ).
         *
         * @param mixed $value
         * @param mixed $value2
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function eq($value, $value2, $message = null, $propertyPath = null)
        {
            if ($value != $value2) {
                $message = $message ?: sprintf(
                    'Value "%s" does not equal expected value "%s".',
                    static::stringify($value),
                    static::stringify($value2)
                );

                throw static::createException($value, $message, static::INVALID_EQ, $propertyPath, array('expected' => $value2));
            }
        }

        /**
         * Assert that two values are the same (using ===).
         *
         * @param mixed $value
         * @param mixed $value2
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function same($value, $value2, $message = null, $propertyPath = null)
        {
            if ($value !== $value2) {
                $message = $message ?: sprintf(
                    'Value "%s" is not the same as expected value "%s".',
                    static::stringify($value),
                    static::stringify($value2)
                );

                throw static::createException($value, $message, static::INVALID_SAME, $propertyPath, array('expected' => $value2));
            }
        }

        /**
         * Assert that two values are not equal (using == ).
         *
         * @param mixed $value1
         * @param mixed $value2
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function notEq($value1, $value2, $message = null, $propertyPath = null)
        {
            if ($value1 == $value2) {
                $message = $message ?: sprintf(
                    'Value "%s" is equal to expected value "%s".',
                    static::stringify($value1),
                    static::stringify($value2)
                );

                throw static::createException($value1, $message,static::INVALID_NOT_EQ, $propertyPath, array('expected' => $value2));
            }
        }

        /**
         * Assert that two values are not the same (using === ).
         *
         * @param mixed $value1
         * @param mixed $value2
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function notSame($value1, $value2, $message = null, $propertyPath = null)
        {
            if ($value1 === $value2) {
                $message = $message ?: sprintf(
                    'Value "%s" is the same as expected value "%s".',
                    static::stringify($value1),
                    static::stringify($value2)
                );

                throw static::createException($value1, $message, static::INVALID_NOT_SAME, $propertyPath, array('expected' => $value2));
            }
        }


        /**
         * Assert that value is a php integer.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function integer($value, $message = null, $propertyPath = null)
        {
            if ( ! is_int($value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not an integer.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_INTEGER, $propertyPath);
            }
        }

        /**
         * Assert that value is a php float.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function float($value, $message = null, $propertyPath = null)
        {
            if ( ! is_float($value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not a float.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_FLOAT, $propertyPath);
            }
        }

        /**
         * Validates if an integer or integerish is a digit.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function digit($value, $message = null, $propertyPath = null)
        {
            if ( ! ctype_digit((string)$value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not a digit.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_DIGIT, $propertyPath);
            }
        }

        /**
         * Assert that value is a php integer'ish.
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function integerish($value, $message = null, $propertyPath = null)
        {
            if (strval(intval($value)) != $value || is_bool($value) || is_null($value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not an integer or a number castable to integer.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_INTEGERISH, $propertyPath);
            }
        }

        /**
         * Assert that value is php boolean
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function boolean($value, $message = null, $propertyPath = null)
        {
            if ( ! is_bool($value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not a boolean.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_BOOLEAN, $propertyPath);
            }
        }

        /**
         * Assert that value is not empty
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function notEmpty($value, $message = null, $propertyPath = null)
        {
            if (empty($value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is empty, but non empty value was expected.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::VALUE_EMPTY, $propertyPath);
            }
        }

        /**
         * Assert that value is empty
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function noContent($value, $message = null, $propertyPath = null)
        {
            if (!empty($value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not empty, but empty value was expected.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::VALUE_NOT_EMPTY, $propertyPath);
            }
        }

        /**
         * Assert that value is not null
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function notNull($value, $message = null, $propertyPath = null)
        {
            if ($value === null) {
                $message = $message ?: sprintf(
                    'Value "%s" is null, but non null value was expected.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::VALUE_NULL, $propertyPath);
            }
        }

        /**
         * Assert that value is a string
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function string($value, $message = null, $propertyPath = null)
        {
            if ( ! is_string($value)) {
                $message = $message ?: sprintf(
                    'Value "%s" expected to be string, type %s given.',
                    static::stringify($value),
                    gettype($value)
                );

                throw static::createException($value, $message, static::INVALID_STRING, $propertyPath);
            }
        }

        /**
         * Assert that value matches a regex
         *
         * @param mixed $value
         * @param string $pattern
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function regex($value, $pattern, $message = null, $propertyPath = null)
        {
            static::string($value, $message);

            if ( ! preg_match($pattern, $value)) {
                $message = $message ?: sprintf(
                    'Value "%s" does not match expression.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_REGEX , $propertyPath, array('pattern' => $pattern));
            }
        }

        /**
         * Assert that string has a given length.
         *
         * @param mixed $value
         * @param int $length
         * @param string $message
         * @param string $propertyPath
         * @param string $encoding
         * @return void
         * @throws Exception
         */
        static public function length($value, $length, $message = null, $propertyPath = null, $encoding = 'utf8')
        {
            static::string($value, $message);

            if (mb_strlen($value, $encoding) !== $length) {
                $message = $message ?: sprintf(
                    'Value "%s" has to be %d exactly characters long, but length is %d.',
                    static::stringify($value),
                    $length,
                    mb_strlen($value, $encoding)
                );

                $constraints = array('length' => $length, 'encoding' => $encoding);

                throw static::createException($value, $message, static::INVALID_LENGTH, $propertyPath, $constraints);
            }
        }

        /**
         * Assert that a string is at least $minLength chars long.
         *
         * @param mixed $value
         * @param int $minLength
         * @param string $message
         * @param string $propertyPath
         * @param string $encoding
         * @return void
         * @throws Exception
         */
        static public function minLength($value, $minLength, $message = null, $propertyPath = null, $encoding = 'utf8')
        {
            static::string($value, $message);

            if (mb_strlen($value, $encoding) < $minLength) {
                $message = $message ?: sprintf(
                    'Value "%s" is too short, it should have more than %d characters, but only has %d characters.',
                    static::stringify($value),
                    $minLength,
                    mb_strlen($value, $encoding)
                );

                $constraints = array('min_length' => $minLength, 'encoding' => $encoding);

                throw static::createException($value, $message, static::INVALID_MIN_LENGTH, $propertyPath, $constraints);
            }
        }

        /**
         * Assert that string value is not longer than $maxLength chars.
         *
         * @param mixed $value
         * @param integer $maxLength
         * @param string $message
         * @param string $propertyPath
         * @param string $encoding
         * @return void
         * @throws Exception
         */
        static public function maxLength($value, $maxLength, $message = null, $propertyPath = null, $encoding = 'utf8')
        {
            static::string($value, $message);

            if (mb_strlen($value, $encoding) > $maxLength) {
                $message = $message ?: sprintf(
                    'Value "%s" is too long, it should have no more than %d characters, but has %d characters.',
                    static::stringify($value),
                    $maxLength,
                    mb_strlen($value, $encoding)
                );

                $constraints = array('max_length' => $maxLength, 'encoding' => $encoding);

                throw static::createException($value, $message, static::INVALID_MAX_LENGTH, $propertyPath, $constraints);
            }
        }

        /**
         * Assert that string length is between min,max lengths.
         *
         * @param mixed $value
         * @param integer $minLength
         * @param integer $maxLength
         * @param string $message
         * @param string $propertyPath
         * @param string $encoding
         * @return void
         * @throws Exception
         */
        static public function betweenLength($value, $minLength, $maxLength, $message = null, $propertyPath = null, $encoding = 'utf8')
        {
            static::string($value, $message);

            if (mb_strlen($value, $encoding) < $minLength) {
                $message = $message ?: sprintf(
                    'Value "%s" is too short, it should have more than %d characters, but only has %d characters.',
                    static::stringify($value),
                    $minLength,
                    mb_strlen($value, $encoding)
                );

                $constraints = array('min_length' => $minLength, 'encoding' => $encoding);

                throw static::createException($value, $message, static::INVALID_MIN_LENGTH, $propertyPath, $constraints);
            }

            if (mb_strlen($value, $encoding) > $maxLength) {
                $message = $message ?: sprintf(
                    'Value "%s" is too long, it should have no more than %d characters, but has %d characters.',
                    static::stringify($value),
                    $maxLength,
                    mb_strlen($value, $encoding)
                );

                $constraints = array('max_length' => $maxLength, 'encoding' => $encoding);

                throw static::createException($value, $message, static::INVALID_MAX_LENGTH, $propertyPath, $constraints);
            }
        }

        /**
         * Assert that string starts with a sequence of chars.
         *
         * @param mixed $string
         * @param string $needle
         * @param string $message
         * @param string $propertyPath
         * @param string $encoding
         * @return void
         * @throws Exception
         */
        static public function startsWith($string, $needle, $message = null, $propertyPath = null, $encoding = 'utf8')
        {
            static::string($string);

            if (mb_strpos($string, $needle, null, $encoding) !== 0) {
                $message = $message ?: sprintf(
                    'Value "%s" does not start with "%s".',
                    static::stringify($string),
                    static::stringify($needle)
                );

                $constraints = array('needle' => $needle, 'encoding' => $encoding);

                throw static::createException($string, $message, static::INVALID_STRING_START, $propertyPath, $constraints);
            }
        }

        /**
         * Assert that string ends with a sequence of chars.
         *
         * @param mixed $string
         * @param string $needle
         * @param string $message
         * @param string $propertyPath
         * @param string $encoding
         * @return void
         * @throws Exception
         */
        static public function endsWith($string, $needle, $message = null, $propertyPath = null, $encoding = 'utf8')
        {
            static::string($string);

            $stringPosition = mb_strlen($string, $encoding) - mb_strlen($needle, $encoding);

            if (mb_strripos($string, $needle, null, $encoding) !== $stringPosition) {
                $message = $message ?: sprintf(
                    'Value "%s" does not end with "%s".',
                    static::stringify($string),
                    static::stringify($needle)
                );

                $constraints = array('needle' => $needle, 'encoding' => $encoding);

                throw static::createException($string, $message, static::INVALID_STRING_END, $propertyPath, $constraints);
            }
        }

        /**
         * Assert that string contains a sequence of chars.
         *
         * @param mixed $string
         * @param string $needle
         * @param string $message
         * @param string $propertyPath
         * @param string $encoding
         * @return void
         * @throws Exception
         */
        static public function contains($string, $needle, $message = null, $propertyPath = null, $encoding = 'utf8')
        {
            static::string($string);

            if (mb_strpos($string, $needle, null, $encoding) === false) {
                $message = $message ?: sprintf(
                    'Value "%s" does not contain "%s".',
                    static::stringify($string),
                    static::stringify($needle)
                );

                $constraints = array('needle' => $needle, 'encoding' => $encoding);

                throw static::createException($string, $message, static::INVALID_STRING_CONTAINS, $propertyPath, $constraints);
            }
        }

        /**
         * Assert that value is in array of choices.
         *
         * @param mixed $value
         * @param array $choices
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function choice($value, array $choices, $message = null, $propertyPath = null)
        {
            if (!Arrays::in($value, $choices, true)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not an element of the valid values: %s',
                    static::stringify($value),
                    implode(", ", array_map('Assert\Assertion::stringify', $choices))
                );

                throw static::createException($value, $message, static::INVALID_CHOICE, $propertyPath, array('choices' => $choices));
            }
        }

        /**
         * Alias of {@see choice()}
         *
         * @throws Exception
         */
        static public function inArray($value, array $choices, $message = null, $propertyPath = null)
        {
            static::choice($value, $choices, $message, $propertyPath);
        }

        /**
         * Assert that value is numeric.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function numeric($value, $message = null, $propertyPath = null)
        {
            if (!is_numeric($value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not numeric.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_NUMERIC, $propertyPath);
            }
        }

        /**
         * Assert that value is array.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function isArray($value, $message = null, $propertyPath = null)
        {
            if (!Arrays::is($value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not an array.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_ARRAY, $propertyPath);
            }
        }

        /**
         * Assert that key exists in array
         *
         * @param mixed $value
         * @param string|integer $key
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function keyExists($value, $key, $message = null, $propertyPath = null)
        {
            static::isArray($value);

            if (!Arrays::exists($key, $value)) {
                $message = $message ?: sprintf(
                    'Array does not contain an element with key "%s"',
                    static::stringify($key)
                );

                throw static::createException($value, $message, static::INVALID_KEY_EXISTS, $propertyPath, array('key' => $key));
            }
        }

        /**
         * Assert that key exists in array and it's value not empty.
         *
         * @param mixed $value
         * @param string|integer $key
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function notEmptyKey($value, $key, $message = null, $propertyPath = null)
        {
            static::keyExists($value, $key, $message, $propertyPath);

            static::notEmpty($value[$key], $message, $propertyPath);
        }

        /**
         * Assert that value is not blank
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function notBlank($value, $message = null, $propertyPath = null)
        {
            if (false === $value || (empty($value) && '0' != $value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is blank, but was expected to contain a value.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_NOT_BLANK, $propertyPath);
            }
        }

        /**
         * Assert that value is instance of given class-name.
         *
         * @param mixed $value
         * @param string $className
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function isInstanceOf($value, $className, $message = null, $propertyPath = null)
        {
            if ( ! ($value instanceof $className)) {
                $message = $message ?: sprintf(
                    'Class "%s" was expected to be instanceof of "%s" but is not.',
                    static::stringify($value),
                    $className
                );

                throw static::createException($value, $message, static::INVALID_INSTANCE_OF, $propertyPath, array('class' => $className));
            }
        }

        /**
         * Assert that value is not instance of given class-name.
         *
         * @param mixed $value
         * @param string $className
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function notIsInstanceOf($value, $className, $message = null, $propertyPath = null)
        {
            if ($value instanceof $className) {
                $message = $message ?: sprintf(
                    'Class "%s" was not expected to be instanceof of "%s".',
                    static::stringify($value),
                    $className
                );

                throw static::createException($value, $message, static::INVALID_NOT_INSTANCE_OF, $propertyPath, array('class' => $className));
            }
        }

        /**
         * Assert that value is subclass of given class-name.
         *
         * @param mixed $value
         * @param string $className
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function subclassOf($value, $className, $message = null, $propertyPath = null)
        {
            if (!is_subclass_of($value, $className)) {
                $message = $message ?: sprintf(
                    'Class "%s" was expected to be subclass of "%s".',
                    static::stringify($value),
                    $className
                );

                throw static::createException($value, $message, static::INVALID_SUBCLASS_OF, $propertyPath, array('class' => $className));
            }
        }

        /**
         * Assert that value is in range of integers.
         *
         * @param mixed $value
         * @param integer $minValue
         * @param integer $maxValue
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function range($value, $minValue, $maxValue, $message = null, $propertyPath = null)
        {
            static::integer($value);

            if ($value < $minValue || $value > $maxValue) {
                $message = $message ?: sprintf(
                    'Number "%s" was expected to be at least "%d" and at most "%d".',
                    static::stringify($value),
                    static::stringify($minValue),
                    static::stringify($maxValue)
                );

                throw static::createException($value, $message, static::INVALID_RANGE, $propertyPath, array('min' => $minValue, 'max' => $maxValue));
            }
        }

        /**
         * Assert that a value is at least as big as a given limit
         *
         * @param mixed $value
         * @param mixed $minValue
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function min($value, $minValue, $message = null, $propertyPath = null)
        {
            static::integer($value);

            if ($value < $minValue) {
                $message = $message ?: sprintf(
                    'Number "%s" was expected to be at least "%d".',
                    static::stringify($value),
                    static::stringify($minValue)
                );

                throw static::createException($value, $message, static::INVALID_MIN, $propertyPath, array('min' => $minValue));
            }
        }

        /**
         * Assert that a number is smaller as a given limit
         *
         * @param mixed $value
         * @param mixed $maxValue
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function max($value, $maxValue, $message = null, $propertyPath = null)
        {
            static::integer($value);

            if ($value > $maxValue) {
                $message = $message ?: sprintf(
                    'Number "%s" was expected to be at most "%d".',
                    static::stringify($value),
                    static::stringify($maxValue)
                );

                throw static::createException($value, $message, static::INVALID_MAX, $propertyPath, array('max' => $maxValue));
            }
        }

        /**
         * Assert that a file exists
         *
         * @param string $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function file($value, $message = null, $propertyPath = null)
        {
            static::string($value, $message);
            static::notEmpty($value, $message);

            if ( ! is_file($value)) {
                $message = $message ?: sprintf(
                    'File "%s" was expected to exist.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_FILE, $propertyPath);
            }
        }

        /**
         * Assert that a directory exists
         *
         * @param string $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function directory($value, $message = null, $propertyPath = null)
        {
            static::string($value, $message);

            if (!is_dir($value)) {
                $message = $message ?: sprintf(
                    'Path "%s" was expected to be a directory.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_DIRECTORY, $propertyPath);
            }
        }

        /**
         * Assert that the value is something readable
         *
         * @param string $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function readable($value, $message = null, $propertyPath = null)
        {
            static::string($value, $message);

            if (!is_readable($value)) {
                $message = $message ?: sprintf(
                    'Path "%s" was expected to be readable.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_READABLE, $propertyPath);
            }
        }

        /**
         * Assert that the value is something writeable
         *
         * @param string $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function writeable($value, $message = null, $propertyPath = null)
        {
            static::string($value, $message);

            if (!is_writeable($value)) {
                $message = $message ?: sprintf(
                    'Path "%s" was expected to be writeable.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_WRITEABLE, $propertyPath);
            }
        }

        /**
         * Assert that value is an email adress (using
         * input_filter/FILTER_VALIDATE_EMAIL).
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function email($value, $message = null, $propertyPath = null)
        {
            static::string($value, $message);

            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $message = $message ?: sprintf(
                    'Value "%s" was expected to be a valid e-mail address.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_EMAIL, $propertyPath);
            } else {
                $host = substr($value, strpos($value, '@') + 1);

                // Likely not a FQDN, bug in PHP FILTER_VALIDATE_EMAIL prior to PHP 5.3.3
                if (version_compare(PHP_VERSION, '5.3.3', '<') && strpos($host, '.') === false) {
                    $message = $message ?: sprintf(
                        'Value "%s" was expected to be a valid e-mail address.',
                        static::stringify($value)
                    );

                    throw static::createException($value, $message, static::INVALID_EMAIL, $propertyPath);
                }
            }
        }

        /**
         * Assert that value is an URL.
         *
         * This code snipped was taken from the Symfony project and modified to the special demands of this method.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         *
         *
         * @link https://github.com/symfony/Validator/blob/master/Constraints/UrlValidator.php
         * @link https://github.com/symfony/Validator/blob/master/Constraints/Url.php
         */
        static public function url($value, $message = null, $propertyPath = null)
        {
            static::string($value, $message, $propertyPath);

            $protocols = array('http', 'https');

            $pattern = '~^
                (%s)://
                (
                    ([\pL\pN\pS-]+\.)+[\pL]+
                        |
                    \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}
                        |
                    \[
                        (?:(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){6})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:::(?:(?:(?:[0-9a-f]{1,4})):){5})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){4})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,1}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){3})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,2}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){2})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,3}(?:(?:[0-9a-f]{1,4})))?::(?:(?:[0-9a-f]{1,4})):)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,4}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,5}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,6}(?:(?:[0-9a-f]{1,4})))?::))))
                    \]
                )
                (:[0-9]+)?
                (/?|/\S+)
            $~ixu';

            $pattern = sprintf($pattern, implode('|', $protocols));

            if (!preg_match($pattern, $value)) {
                $message = $message ?: sprintf(
                    'Value "%s" was expected to be a valid URL starting with http or https',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_URL, $propertyPath);
            }

        }

        /**
         * Assert that value is alphanumeric.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function alnum($value, $message = null, $propertyPath = null)
        {
            try {
                static::regex($value, '(^([a-zA-Z]{1}[a-zA-Z0-9]*)$)');
            } catch(AssertionFailedException $e) {
                $message = $message ?: sprintf(
                    'Value "%s" is not alphanumeric, starting with letters and containing only letters and numbers.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_ALNUM, $propertyPath);
            }
        }

        /**
         * Assert that the value is boolean True.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function true($value, $message = null, $propertyPath = null)
        {
            if ($value !== true) {
                $message = $message ?: sprintf(
                    'Value "%s" is not TRUE.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_TRUE, $propertyPath);
            }
        }

        /**
         * Assert that the value is boolean False.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function false($value, $message = null, $propertyPath = null)
        {
            if ($value !== false) {
                $message = $message ?: sprintf(
                    'Value "%s" is not FALSE.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_FALSE, $propertyPath);
            }
        }

        /**
         * Assert that the class exists.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function classExists($value, $message = null, $propertyPath = null)
        {
            if (!class_exists($value)) {
                $message = $message ?: sprintf(
                    'Class "%s" does not exist.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_CLASS, $propertyPath);
            }
        }

        /**
         * Assert that the class implements the interface
         *
         * @param mixed $class
         * @param string $interfaceName
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function implementsInterface($class, $interfaceName, $message = null, $propertyPath = null)
        {
            $reflection = new \ReflectionClass($class);

            if (!$reflection->implementsInterface($interfaceName)) {
                $message = $message ?: sprintf(
                    'Class "%s" does not implement interface "%s".',
                    static::stringify($class),
                    static::stringify($interfaceName)
                );

                throw static::createException($class, $message, static::INTERFACE_NOT_IMPLEMENTED, $propertyPath, array('interface' => $interfaceName));
            }
        }

        /**
         * Assert that the given string is a valid json string.
         *
         * NOTICE:
         * Since this does a json_decode to determine its validity
         * you probably should consider, when using the variable
         * content afterwards, just to decode and check for yourself instead
         * of using this assertion.
         *
         * @param mixed $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function isJsonString($value, $message = null, $propertyPath = null)
        {
            if (null === json_decode($value) && JSON_ERROR_NONE !== json_last_error()) {
                $message = $message ?: sprintf(
                    'Value "%s" is not a valid JSON string.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_JSON_STRING, $propertyPath);
            }
        }

        /**
         * Assert that the given string is a valid UUID
         *
         * Uses code from {@link https://github.com/ramsey/uuid} that is MIT licensed.
         *
         * @param string $value
         * @param string $message
         * @param string $propertyPath
         * @return void
         * @throws Exception
         */
        static public function uuid($value, $message = null, $propertyPath = null)
        {
            $value = str_replace(
                array(
                    'urn:',
                    'uuid:',
                    '{', '}'
                ),
                '',
                $value
            );

            if ($value === '00000000-0000-0000-0000-000000000000') {
                return;
            }

            if (!preg_match('/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[1-5][0-9A-Fa-f]{3}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/', $value)) {
                $message = $message ?: sprintf(
                    'Value "%s" is not a valid UUID.',
                    static::stringify($value)
                );

                throw static::createException($value, $message, static::INVALID_UUID, $propertyPath);
            }
        }

        /**
         * Assert that the count of countable is equal to count.
         *
         * @param array|\Countable $countable
         * @param int              $count
         * @param string           $message
         * @param string           $propertyPath
         * @return void
         * @throws Exception
         */
        static public function count($countable, $count, $message = null, $propertyPath = null)
        {
            if ($count !== count($countable)) {
                $message = $message ?: sprintf(
                    'List does not contain exactly "%d" elements.',
                    static::stringify($countable),
                    static::stringify($count)
                );

                throw static::createException($countable, $message, static::INVALID_COUNT, $propertyPath, array('count' => $count));
            }
        }

        /**
         * static call handler to implement:
         *  - "null or assertion" delegation
         *  - "all" delegation
         */
        static public function __callStatic($method, $args)
        {
            if (strpos($method, "nullOr") === 0) {
                if (!Arrays::exists(0, $args)) {
                    throw new BadMethodCallException("Missing the first argument.");
                }

                if ($args[0] === null) {
                    return;
                }

                $method = substr($method, 6);

                return call_user_func_array(array(get_called_class(), $method), $args);
            }

            if (strpos($method, "all") === 0) {
                if (!Arrays::exists(0, $args)) {
                    throw new BadMethodCallException("Missing the first argument.");
                }

                static::isArray($args[0]);

                $method      = substr($method, 3);
                $values      = array_shift($args);
                $calledClass = get_called_class();

                foreach ($values as $value) {
                    call_user_func_array(array($calledClass, $method), array_merge(array($value), $args));
                }

                return;
            }

            throw new BadMethodCallException("No assertion Assertion#" . $method . " exists.");
        }

        /**
         * Make a string version of a value.
         *
         * @param mixed $value
         * @return string
         */
        static private function stringify($value)
        {
            if (is_bool($value)) {
                return $value ? '<TRUE>' : '<FALSE>';
            }

            if (is_scalar($value)) {
                $val = (string) $value;

                if (strlen($val) > 100) {
                    $val = substr($val, 0, 97) . '...';
                }

                return $val;
            }

            if (Arrays::is($value)) {
                return '<ARRAY>';
            }

            if (is_object($value)) {
                return get_class($value);
            }

            if (is_resource($value)) {
                return '<RESOURCE>';
            }

            if ($value === null) {
                return '<NULL>';
            }

            return 'unknown';
        }
    }

