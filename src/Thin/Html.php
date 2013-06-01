<?php
    /**
     * HTML class
     * @author      Gerald Plusquellec
     */

    namespace Thin;
    class Html
    {
        public static $macros = array();
        const encoding = 'UTF-8';

        public static function macro($name, $macro)
        {
            static::$macros[$name] = $macro;
        }

        public static function entities($value)
        {
            return htmlentities($value, ENT_QUOTES, static::encoding, false);
        }

        public static function decode($value)
        {
            return html_entity_decode($value, ENT_QUOTES, static::encoding);
        }

        public static function specialchars($value)
        {
            return htmlspecialchars($value, ENT_QUOTES, static::encoding, false);
        }

        public static function escape($value)
        {
            return static::decode(static::entities($value));
        }

        public static function script($url, $attributes = array())
        {
            return '<script src="' . $url . '"' . static::attributes($attributes) . '></script>' . PHP_EOL;
        }

        public static function style($url, $attributes = array())
        {
            $defaults = array('media' => 'all', 'type' => 'text/css', 'rel' => 'stylesheet');
            $attributes = $attributes + $defaults;
            return '<link href="' . $url . '"' . static::attributes($attributes) . '>' . PHP_EOL;
        }

        public static function tag($tag, $value = '', $attributes = array())
        {
            $tag = Inflector::lower($tag);
            if ($tag == 'meta') {
                return '<' . $tag . static::attributes($attributes) . ' />';
            }
            return '<' . $tag . static::attributes($attributes) . '>' . static::entities($value) . '</' . $tag . '>';
        }

        public static function link($url, $title = null, $attributes = array())
        {

            if (null === $title) $title = $url;
            return '<a href="' . $url . '"' . static::attributes($attributes) . '>' . static::entities($title) . '</a>';
        }

        public static function mailto($email, $title = null, $attributes = array())
        {
            $email = static::email($email);
            if (null === $title) $title = $email;
            $email = '&#109;&#097;&#105;&#108;&#116;&#111;&#058;' . $email;
            return '<a href="' . $email . '"' . static::attributes($attributes) . '>' . static::entities($title) . '</a>';
        }

        public static function email($email)
        {
            return repl('@', '&#64;', static::obfuscate($email));
        }

        public static function image($url, $alt = '', $attributes = array())
        {
            $attributes['alt'] = $alt;
            return '<img src="' . $url . '"' . static::attributes($attributes) . '>';
        }

        public static function ol($list, $attributes = array())
        {
            return static::listing('ol', $list, $attributes);
        }

        public static function ul($list, $attributes = array())
        {
            return static::listing('ul', $list, $attributes);
        }

        private static function listing($type, $list, $attributes = array())
        {
            $html = '';

            if (count($list) == 0) return $html;

            foreach ($list as $key => $value) {
                // If the value is an array, we will recurse the function so that we can
                // produce a nested list within the list being built. Of course, nested
                // lists may exist within nested lists, etc.
                if (is_array($value)) {
                    if (is_int($key)) {
                        $html .= static::listing($type, $value);
                    } else {
                        $html .= '<li>' . $key . static::listing($type, $value) . '</li>';
                    }
                } else {
                    $html .= '<li>' . static::entities($value) . '</li>';
                }
            }

            return '<' . $type . static::attributes($attributes) . '>' . $html . '</' . $type . '>';
        }

        public static function dl($list, $attributes = array())
        {
            $html = '';

            if (count($list) == 0) return $html;

            foreach ($list as $term => $description) {
                $html .= '<dt>' . static::entities($term) . '</dt>';
                $html .= '<dd>' . static::entities($description) . '</dd>';
            }

            return '<dl' . static::attributes($attributes) . '>' . $html . '</dl>';
        }

        public static function attributes($attributes)
        {
            $html = array();

            foreach ((array) $attributes as $key => $value) {
                // For numeric keys, we will assume that the key and the value are the
                // same, as this will convert HTML attributes such as "required" that
                // may be specified as required="required", etc.
                if (is_numeric($key)) $key = $value;

                if (null !== $value) {
                    $html[] = $key . '="' . static::entities($value) . '"';
                }
            }
            return (count($html) > 0) ? ' ' . implode(' ', $html) : '';
        }

        protected static function obfuscate($value)
        {
            $safe = '';

            foreach (str_split($value) as $letter) {
                switch (rand(1, 3)) {
                    case 1:
                        $safe .= '&#' . ord($letter) . ';';
                        break;

                    case 2:
                        $safe .= '&#x' . dechex(ord($letter)) . ';';
                        break;

                    case 3:
                        $safe .= $letter;
                }
            }

            return $safe;
        }

        public static function __callStatic($method, $parameters)
        {
            if (ake($method, static::$macros)) {
                return call_user_func_array(static::$macros[$method], $parameters);
            }
            throw new Exception("Method [$method] does not exist.");
        }
    }
