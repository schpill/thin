<?php
    namespace Thin\Html;
    class Render
    {
        public static $_headers = array(
            'json' => 'Content-Type: application/json',
            'xml' => 'Content-Type: text/xml',
        );

        public static function render($type, $content)
        {
            if (ake($type, static::$_headers)) {
                if (!headers_sent()) {
                    header(static::$_headers[$type]);
                    die($content);
                }
            }
        }

        public static function json($content, $encoding = false)
        {
            if (true === $encoding) {
                $content = json_encode($content);
            }
            static::render('json', $content);
        }

        public static function xml($content)
        {
            static::render('xml', $content);
        }
    }
