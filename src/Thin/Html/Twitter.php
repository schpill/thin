<?php
    /**
     * Twitter class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    namespace Thin\Html;
    use Thin\Html as Html;
    class Twitter
    {
        public static function code($code, $isPreScrollable = false, array $attributes = array())
        {
            if ($isPreScrollable) {
                if (!array_key_exists('class', $attributes)) {
                    $attributes['class'] = array();
                }

                if (is_array($attributes['class'])) {
                    $attributes['class'][] = 'pre-scrollable';
                } elseif (false === strpos($attributes['class'], 'pre-scrollable')) {
                    $attributes['class'] .= ' pre-scrollable';
                }
            }

            return sprintf('<pre%s>%s</pre>', Html::attributes($attributes), Html::escape($code));
        }

        public static function blockquote($text, $source = null, $cite = null, $citeTitle = null, array $attributes = array())
        {
            if (null !== $source) {
                $citeStr = null;
                if (null !== $cite) {
                    $citeStr = sprintf('<cite title="%s">%s</cite>', Html::escape($citeTitle), Html::escape($cite));
                }

                $source = sprintf(' <small>%s</small>', Html::escape($source) . ' ' . $citeStr);
            }

            return sprintf('<blockquote%s>%s</blockquote>', (count($attributes) ? Html::attributes($attributes) : ''), Html::escape($text) . $source);
        }

        public static function lead($text, array $attributes = array())
        {
            if (!array_key_exists('class', $attributes)) {
                $attributes['class'] = array('lead');
            }

            if (is_array($attributes['class'])) {
                $attributes['class'][] = 'lead';
            } elseif (false === strpos($attributes['class'], 'lead')) {
                $attributes['class'] .= ' lead';
            }

            $attributes['class'] = array_unique($attributes['class']);

            return sprintf('<p%s>%s</p>', Html::attributes($attributes), Html::escape($text));
        }

        public static function circledImage($src, array $attributes = array())
        {
            return static::_renderImage($src, 'img-circle', $attributes);
        }

        public static function roundedImage($src, array $attributes = array())
        {
            return static::_renderImage($src, 'img-rounded', $attributes);
        }

        public static function polaroidImage($src, array $attributes = array())
        {
            return static::_renderImage($src, 'img-polaroid', $attributes);
        }

        /**
        * Renders the image tag
        *
        * @param string $src
        * @param array $attributes
        *
        * @return string
        */
        protected static function _renderImage($src, $className, array $attributes = array())
        {
            if (is_array($attributes['class'])) {
                $attributes['class'][] = $className;
            } else {
                $attributes['class'] .= ' ' . $className;
            }

            return sprintf('<img src="%s"%s />', Html::escape($src), Html::attributes($attributes));
        }
    }
