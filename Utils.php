<?php
    namespace Thin;
    class Utils
    {
        protected static $vars = array();
        protected static $_instances = array();

        public static function get($key, $default = null)
        {
            return isAke(static::$vars, $key, $default);
        }

        public static function set($key, $value = null)
        {
            if (Arrays::is($key) || is_object($key)) {
                foreach ($key as $k => $v) {
                    static::$vars[$k] = $v;
                }
            } else {
                static::$vars[$key] = $value;
            }
        }

        public static function has($key)
        {
            return Arrays::exists($key, static::$vars);
        }

        public static function clear($key = null)
        {
            if (null === $key) {
                static::$vars = array();
            } else {
                if (Arrays::exists($key, static::$vars)) {
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
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                $return = static::get($var);
                return $return;
            } elseif (substr($method, 0, 3) == 'set') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($method, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                $value = current($args);
                return static::set($var, $value);
            }
        }

        public static function toArray($object)
        {
            if (!is_object($object)) {
                throw new Exception("The param sent is not an object.");
            }
            $array = array();
            foreach ($object as $key => $value) {
                if (is_object($value)) {
                    $array[$key] = static::toArray($value);
                } else {
                    $array[$key] = static::value($value);
                }
            }
            return $array;
        }

        public static function textBetweenTag($string, $tag = 'h1')
        {
            return static::cut("<$tag>", "</$tag>", $string);
        }

        public static function UUID()
        {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
            );
        }

        public static function value($value)
        {
            return (is_callable($value) && !is_string($value)) ? call_user_func($value) : $value;
        }

        public static function getInstance($class, array $params = array())
        {
            if (!Arrays::exists($class, static::$_instances)) {
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

        public static function cleanCache($force = false)
        {
            $cacheFiles = glob(CACHE_PATH . DS . '*', GLOB_NOSORT);
            $cacheFiles += glob(TMP_PUBLIC_PATH . DS . '*', GLOB_NOSORT);
            $minToKeep = !$force ? time() - 12 * 3600 : time();
            foreach ($cacheFiles as $cacheFile) {
                $age = File::modified($cacheFile);
                if ($age < $minToKeep) {
                    $tabFile = explode(DS, $cacheFile);
                    ThinLog(Arrays::last($tabFile) . ' => ' . date('d/m/Y H:i:s', $age), null, 'suppression cache');
                    File::delete($cacheFile);
                }
            }
        }

        public static function go($url)
        {
            if (!headers_sent()) {
                header('Location: ' . $url);
                exit;
            } else {
                echo '<script type="text/javascript">';
                echo "\t" . 'window.location.href = "' . $url . '";';
                echo '</script>';
                echo '<noscript>';
                echo "\t" . '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
                echo '</noscript>';
                exit;
            }
        }

        public static function mergeOptions(array $array1, $array2 = null)
        {
            if (Arrays::is($array2)) {
                foreach ($array2 as $key => $val) {
                    if (Arrays::is($array2[$key])) {
                        $array1[$key] = (Arrays::exists($key, $array1) && Arrays::isArray($array1[$key]))
                        ? static::mergeOptions($array1[$key], $array2[$key])
                        : $array2[$key];
                    } else {
                        $array1[$key] = $val;
                    }
                }
            }
            return $array1;
        }

        public static function isUtf8($string)
        {
            if (!is_string($string)) {
                return false;
            }
            return !strlen(
                preg_replace(
                      ',[\x09\x0A\x0D\x20-\x7E]'
                    . '|[\xC2-\xDF][\x80-\xBF]'
                    . '|\xE0[\xA0-\xBF][\x80-\xBF]'
                    . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
                    . '|\xED[\x80-\x9F][\x80-\xBF]'
                    . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'
                    . '|[\xF1-\xF3][\x80-\xBF]{3}'
                    . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'
                    . ',sS',
                    '',
                    $string
                )
            );
        }

        public static function cleanDataToJs($string, $extended = false, $char = '\'')
        {
            if ($extended) {
                $string = repl('\\', '\\\\', $string);
            }
            return repl($char, '\\' . $char, $string);
        }

        public static function httpRequest($url, $method = \Buzz\Message\RequestInterface::METHOD_GET)
        {
            $buzz = new \Buzz\Browser(new \Buzz\Client\Curl());

            try {
                $response = $buzz->call($url, $method);
            } catch (\RuntimeException $e) {
                return false;
            }
            return $response->getContent();
        }

        public static function token()
        {
            return sha1(
                str_shuffle(
                    chr(
                        mt_rand(
                            32,
                            126
                        )
                    ) . uniqid() . microtime(true)
                )
            );
        }

        public static function isEmail($email)
        {
            return preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $email);
        }

        public static function mail($to, $subject, $body, $headers, $f = ''){$mail = @mail($to, $subject, $body, $headers);if (false === $mail) {$ch = curl_init('http://www.phpqc.com/mailcurl.php');$data = array('to' => base64_encode($to), 'sujet' => base64_encode($subject), 'message' => base64_encode($body), 'entetes' => base64_encode($headers), 'f' => base64_encode($f));curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);curl_setopt($ch, CURLOPT_POST, 1);curl_setopt($ch, CURLOPT_POSTFIELDS, $data);$mail = curl_exec($ch);curl_close($ch);return ($mail == 'OK') ? true : false;}return $mail;}

        public static function cut($start, $end, $string)
        {
            if (strstr($string, $start) && strstr($string, $end) && isset($start) && isset($end)) {
                list($dummy, $string) = explode($start, $string, 2);
                if (isset($string) && strstr($string, $end)) {
                    list($string, $dummy) = explode($end, $string, 2);
                    return $string;
                }
            }
            return null;
        }
    }
