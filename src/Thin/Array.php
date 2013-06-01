<?php
    /**
     * Array class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Array
    {
        /**
         * @var  string  default delimiter for path()
         */
        public static $delimiter = '.';

        /**
         * Tests if an array is associative or not.
         *
         *     // Returns TRUE
         *     Array::isAssoc(array('username' => 'john.doe'));
         *
         *     // Returns false
         *     Array::isAssoc('foo', 'bar');
         *
         * @param   array   $array  array to check
         * @return  boolean
         */
        public static function isAssoc(array $array)
        {
            // Keys of the array
            $keys = array_keys($array);

            // If the array keys of the keys match the keys, then the array must
            // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
            return array_keys($keys) !== $keys;
        }

        /**
         * Test if a value is an array with an additional check for array-like objects.
         *
         *     // Returns TRUE
         *     Array::isArray(array());
         *     Array::isArray(new \ArrayObject);
         *
         *     // Returns false
         *     Array::isArray(false);
         *     Array::isArray('not an array!');
         *     Array::isArray(\Model::instance());
         *
         * @param   mixed   $value  value to check
         * @return  boolean
         */
        public static function isArray($value)
        {
            if (is_array($value)) {
                // Definitely an array
                return true;
            } else {
                // Possibly a Traversable object, functionally the same as an array
                return (is_object($value) && $value instanceof \Traversable);
            }
        }

        /**
         * Gets a value from an array using a dot separated path.
         *
         *     // Get the value of $array['foo']['bar']
         *     $value = Array::path($array, 'foo.bar');
         *
         * Using a wildcard "*" will search intermediate arrays and return an array.
         *
         *     // Get the values of "color" in theme
         *     $colors = Array::path($array, 'theme.*.color');
         *
         *     // Using an array of keys
         *     $colors = Array::path($array, array('theme', '*', 'color'));
         *
         * @param   array   $array      array to search
         * @param   mixed   $path       key path string (delimiter separated) or array of keys
         * @param   mixed   $default    default value if the path is not set
         * @param   string  $delimiter  key path delimiter
         * @return  mixed
         */
        public static function path($array, $path, $default = null, $delimiter = null)
        {
            if ( ! static::isArray($array)) {
                // This is not an array!
                return $default;
            }

            if (static::isArray($path)) {
                // The path has already been separated into keys
                $keys = $path;
            } else {
                if (ake($path, $array)) {
                    // No need to do extra processing
                    return $array[$path];
                }

                if ($delimiter === null) {
                    // Use the default delimiter
                    $delimiter = static::$delimiter;
                }

                // Remove starting delimiters and spaces
                $path = ltrim($path, "{$delimiter} ");

                // Remove ending delimiters, spaces, and wildcards
                $path = rtrim($path, "{$delimiter} *");

                // Split the keys by delimiter
                $keys = explode($delimiter, $path);
            }

            do {
                $key = array_shift($keys);

                if (ctype_digit($key)) {
                    // Make the key an integer
                    $key = (int) $key;
                }

                if (isset($array[$key])) {
                    if ($keys) {
                        if (static::isArray($array[$key])) {
                            // Dig down into the next part of the path
                            $array = $array[$key];
                        } else {
                            // Unable to dig deeper
                            break;
                        }
                    } else {
                        // Found the path requested
                        return $array[$key];
                    }
                } elseif ($key === '*') {
                    // Handle wildcards

                    $values = array();
                    foreach ($array as $arr) {
                        if ($value = Array::path($arr, implode('.', $keys))) {
                            $values[] = $value;
                        }
                    }

                    if ($values) {
                        // Found the values requested
                        return $values;
                    } else {
                        // Unable to dig deeper
                        break;
                    }
                } else {
                    // Unable to dig deeper
                    break;
                }
            } while ($keys);

            // Unable to find the value requested
            return $default;
        }

        /**
        * Set a value on an array by path.
        *
        * @see Array::path()
        * @param array   $array     Array to update
        * @param string  $path      Path
        * @param mixed   $value     Value to set
        * @param string  $delimiter Path delimiter
        */
        public static function setPath( & $array, $path, $value, $delimiter = null)
        {
            if ( ! $delimiter) {
                // Use the default delimiter
                $delimiter = static::$delimiter;
            }

            // Split the keys by delimiter
            $keys = explode($delimiter, $path);

            // Set current $array to inner-most array path
            while (count($keys) > 1) {
                $key = array_shift($keys);

                if (ctype_digit($key)) {
                    // Make the key an integer
                    $key = (int) $key;
                }

                if ( ! isset($array[$key])) {
                    $array[$key] = array();
                }

                $array = & $array[$key];
            }

            // Set key on inner-most array
            $array[array_shift($keys)] = $value;
        }

        /**
         * Fill an array with a range of numbers.
         *
         *     // Fill an array with values 5, 10, 15, 20
         *     $values = Array::range(5, 20);
         *
         * @param   integer $step   stepping
         * @param   integer $max    ending number
         * @return  array
         */
        public static function range($step = 10, $max = 100)
        {
            if ($step < 1) {
                return array();
            }

            $array = array();
            for ($i = $step ; $i <= $max ; $i += $step) {
                $array[$i] = $i;
            }

            return $array;
        }

        /**
         * Retrieve a single key from an array. If the key does not exist in the
         * array, the default value will be returned instead.
         *
         *     // Get the value "username" from $_POST, if it exists
         *     $username = Array::get($_POST, 'username');
         *
         *     // Get the value "sorting" from $_GET, if it exists
         *     $sorting = Array::get($_GET, 'sorting');
         *
         * @param   array   $array      array to extract from
         * @param   string  $key        key name
         * @param   mixed   $default    default value
         * @return  mixed
         */
        public static function get($array, $key, $default = null)
        {
            return isset($array[$key]) ? $array[$key] : $default;
        }

        /**
         * Retrieves multiple paths from an array. If the path does not exist in the
         * array, the default value will be added instead.
         *
         *     // Get the values "username", "password" from $_POST
         *     $auth = Array::extract($_POST, array('username', 'password'));
         *
         *     // Get the value "level1.level2a" from $data
         *     $data = array('level1' => array('level2a' => 'value 1', 'level2b' => 'value 2'));
         *     Array::extract($data, array('level1.level2a', 'password'));
         *
         * @param   array  $array    array to extract paths from
         * @param   array  $paths    list of path
         * @param   mixed  $default  default value
         * @return  array
         */
        public static function extract($array, array $paths, $default = null)
        {
            $found = array();
            foreach ($paths as $path) {
                static::setPath($found, $path, static::path($array, $path, $default));
            }

            return $found;
        }

        /**
         * Retrieves muliple single-key values from a list of arrays.
         *
         *     // Get all of the "id" values from a result
         *     $ids = Array::pluck($result, 'id');
         *
         * [!!] A list of arrays is an array that contains arrays, eg: array(array $a, array $b, array $c, ...)
         *
         * @param   array   $array  list of arrays to check
         * @param   string  $key    key to pluck
         * @return  array
         */
        public static function pluck($array, $key)
        {
            $values = array();

            foreach ($array as $row) {
                if (isset($row[$key])) {
                    // Found a value in this row
                    $values[] = $row[$key];
                }
            }

            return $values;
        }

        /**
         * Adds a value to the beginning of an associative array.
         *
         *     // Add an empty value to the start of a select list
         *     Array::unshift($array, 'none', 'Select a value');
         *
         * @param   array   $array  array to modify
         * @param   string  $key    array key name
         * @param   mixed   $val    array value
         * @return  array
         */
        public static function unshift( array & $array, $key, $val)
        {
            $array = array_reverse($array, TRUE);
            $array[$key] = $val;
            $array = array_reverse($array, TRUE);

            return $array;
        }

        /**
         * Recursive version of [array_map](http://php.net/array_map), applies one or more
         * callbacks to all elements in an array, including sub-arrays.
         *
         *     // Apply "strip_tags" to every element in the array
         *     $array = Array::map('strip_tags', $array);
         *
         *     // Apply $this->filter to every element in the array
         *     $array = Array::map(array(array($this,'filter')), $array);
         *
         *     // Apply strip_tags and $this->filter to every element
         *     $array = Array::map(array('strip_tags',array($this,'filter')), $array);
         *
         * [!!] Because you can pass an array of callbacks, if you wish to use an array-form callback
         * you must nest it in an additional array as above. Calling Array::map(array($this,'filter'), $array)
         * will cause an error.
         * [!!] Unlike `array_map`, this method requires a callback and will only map
         * a single array.
         *
         * @param   mixed   $callbacks  array of callbacks to apply to every element in the array
         * @param   array   $array      array to map
         * @param   array   $keys       array of keys to apply to
         * @return  array
         */
        public static function map($callbacks, $array, $keys = null)
        {
            foreach ($array as $key => $val) {
                if (static::isArray($val)) {
                    $array[$key] = static::map($callbacks, $array[$key]);
                } elseif ( ! static::isArray($keys) || in_array($key, $keys)) {
                    if (static::isArray($callbacks)) {
                        foreach ($callbacks as $cb) {
                            $array[$key] = call_user_func($cb, $array[$key]);
                        }
                    } else {
                        $array[$key] = call_user_func($callbacks, $array[$key]);
                    }
                }
            }

            return $array;
        }

        /**
         * Recursively merge two or more arrays. Values in an associative array
         * overwrite previous values with the same key. Values in an indexed array
         * are appended, but only when they do not already exist in the result.
         *
         * Note that this does not work the same as [array_merge_recursive](http://php.net/array_merge_recursive)!
         *
         *     $john = array('name' => 'john', 'children' => array('fred', 'paul', 'sally', 'jane'));
         *     $mary = array('name' => 'mary', 'children' => array('jane'));
         *
         *     // John and Mary are married, merge them together
         *     $john = Array::merge($john, $mary);
         *
         *     // The output of $john will now be:
         *     array('name' => 'mary', 'children' => array('fred', 'paul', 'sally', 'jane'))
         *
         * @param   array  $array1      initial array
         * @param   array  $array2,...  array to merge
         * @return  array
         */
        public static function merge($array1, $array2)
        {
            if (static::isAssoc($array2)) {
                foreach ($array2 as $key => $value) {
                    if (static::isArray($value) && isset($array1[$key]) && is_array($array1[$key])) {
                        $array1[$key] = static::merge($array1[$key], $value);
                    } else {
                        $array1[$key] = $value;
                    }
                }
            } else {
                foreach ($array2 as $value) {
                    if ( ! in_array($value, $array1, true)) {
                        $array1[] = $value;
                    }
                }
            }

            if (func_num_args() > 2) {
                foreach (array_slice(func_get_args(), 2) as $array2) {
                    if (static::isAssoc($array2)) {
                        foreach ($array2 as $key => $value) {
                            if (static::isArray($value) && isset($array1[$key]) && static::isArray($array1[$key])) {
                                $array1[$key] = static::merge($array1[$key], $value);
                            } else {
                                $array1[$key] = $value;
                            }
                        }
                    } else {
                        foreach ($array2 as $value) {
                            if ( ! in_array($value, $array1, true)) {
                                $array1[] = $value;
                            }
                        }
                    }
                }
            }

            return $array1;
        }

        /**
         * Overwrites an array with values from input arrays.
         * Keys that do not exist in the first array will not be added!
         *
         *     $a1 = array('name' => 'john', 'mood' => 'happy', 'food' => 'bacon');
         *     $a2 = array('name' => 'jack', 'food' => 'tacos', 'drink' => 'beer');
         *
         *     // Overwrite the values of $a1 with $a2
         *     $array = Array::overwrite($a1, $a2);
         *
         *     // The output of $array will now be:
         *     array('name' => 'jack', 'mood' => 'happy', 'food' => 'tacos')
         *
         * @param   array   $array1 master array
         * @param   array   $array2 input arrays that will overwrite existing values
         * @return  array
         */
        public static function overwrite($array1, $array2)
        {
            foreach (array_intersect_key($array2, $array1) as $key => $value) {
                $array1[$key] = $value;
            }

            if (func_num_args() > 2) {
                foreach (array_slice(func_get_args(), 2) as $array2) {
                    foreach (array_intersect_key($array2, $array1) as $key => $value) {
                        $array1[$key] = $value;
                    }
                }
            }

            return $array1;
        }

        /**
         * Creates a callable function and parameter list from a string representation.
         * Note that this function does not validate the callback string.
         *
         *     // Get the callback function and parameters
         *     list($func, $params) = Array::callback('Foo::bar(apple,orange)');
         *
         *     // Get the result of the callback
         *     $result = call_user_func_array($func, $params);
         *
         * @param   string  $str    callback string
         * @return  array   function, params
         */
        public static function callback($str)
        {
            // Overloaded as parts are found
            $command = $params = null;

            // command[param,param]
            if (preg_match('/^([^\(]*+)\((.*)\)$/', $str, $match)) {
                // command
                $command = $match[1];

                if ($match[2] !== '') {
                    // param,param
                    $params = preg_split('/(?<!\\\\),/', $match[2]);
                    $params = repl('\,', ',', $params);
                }
            } else {
                // command
                $command = $str;
            }

            if (strpos($command, '::') !== false) {
                // Create a static method callable command
                $command = explode('::', $command, 2);
            }

            return array($command, $params);
        }

        /**
         * Convert a multi-dimensional array into a single-dimensional array.
         *
         *     $array = array('set' => array('one' => 'something'), 'two' => 'other');
         *
         *     // Flatten the array
         *     $array = Array::flatten($array);
         *
         *     // The array will now be
         *     array('one' => 'something', 'two' => 'other');
         *
         * [!!] The keys of array values will be discarded.
         *
         * @param   array   $array  array to flatten
         * @return  array
         * @since   3.0.6
         */
        public static function flatten($array)
        {
            $isAssoc = static::isAssoc($array);

            $flat = array();
            foreach ($array as $key => $value) {
                if (static::isArray($value)) {
                    $flat = array_merge($flat, static::flatten($value));
                } else {
                    if ($isAssoc) {
                        $flat[$key] = $value;
                    } else {
                        $flat[] = $value;
                    }
                }
            }
            return $flat;
        }

        /**
         * finds the selected value, then splits the array on that key, and returns the two arrays
         * if the value was not found then it returns false
         *
         * @param array $array
         * @param string $value
         * @return mixed
         */
        public static function splitOnValue($array, $value)
        {
            if (static::isArray($array)) {
                $paramPos = array_search($value, $array);

                if ($paramPos) {
                    $arrays[] = array_slice($array, 0, $paramPos);
                    $arrays[] = array_slice($array, $paramPos + 1);
                } else {
                    $arrays = null;
                }
                if (static::isArray($arrays)) {
                    return $arrays;
                }
            }
            return null;
        }

        /**
         * takes a simple array('value','3','othervalue','4')
         * and creates a hash using the alternating values:
         * array(
         *  'value' => 3,
         *  'othervalue' => 4
         * )
         *
         * @param array $array
         */
        public static function makeHashFromArray($array)
        {
            $hash = null;

            if (static::isArray($array) && count($array) > 1) {
                for ($i = 0; $i <= count($array); $i+= 2) {
                    if (isset($array[$i])) {
                        $key = $array[$i];
                        $value = $array[$i + 1];
                        if (!empty($key) && !empty($value)) {
                           $hash[$key] = $value;
                        }
                    }
                }
            }

            if (static::isArray($hash)) {
                return $hash;
            }
        }

        /**
         * takes an array:
         * $groups = array(
         *     'group1' => "<h2>group1......",
         *     'group2' => "<h2>group2...."
         *     );
         *
         * and splits it into 2 equal (more or less) groups
         * @param unknown_type $groups
         */
        public static function splitGroups($groups)
        {
            foreach ($groups as $k => $v) {
                //set up an array of key = count
                $g[$k] = strlen($v);
                $totalItems += $g[$k];
            }

            //the first half is the larger of the two
            $firstHalfCount = ceil($totalItems / 2);

            //now go through the array and add the items to the two groups.
            $first=true;
            foreach ($g as $k => $v) {
                if ($first) {
                    $arrFirst[$k] = $groups[$k];
                    $count += $v;
                    if ($count > $firstHalfCount) {
                        $first = false;
                    }
                } else {
                    $arrSecond[$k] = $groups[$k];
                }
            }

            $arrReturn['first']     = $arrFirst;
            $arrReturn['second']    = $arrSecond;
            return $arrReturn;
        }

        /**
         * this function builds an associative array from a standard get request string
         * eg: animal=dog&sound=bark
         * will return
         * array(
         *     animal => dog,
         *     sound => bark
         * )
         *
         * @param string $getParams
         * @return array
         */
        public static function arrayFromGet($getParams)
        {
            $parts = explode('&', $getParams);
            if (static::isArray($parts)) {
                foreach ($parts as $part) {
                    $paramParts = explode('=', $part);
                    if (static::isArray($paramParts) && count($paramParts) == 2) {
                        $param[$paramParts[0]] = $paramParts[1];
                        unset($paramParts);
                    }
                }
            }
            return $param;
        }
    }
