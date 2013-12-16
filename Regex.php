<?php
    /**
     * Regex class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Regex
    {
        /**
         * removes the trailing slash
         *
         * @param string $string
         * @return string
         */
        public static function stripTrailingSlash($string)
        {
            return preg_replace("/\/$/", '', $string);
        }

        /**
         * strips the file extension
         *
         * @param string $string
         * @return string
         */
        public static function stripFileExtension($string, $asArray = false)
        {
            $regexp = "|\.\w{1,5}$|";
            $new = preg_replace($regexp, "", $string);
            $suf = substr($string, strlen($new)+1);
            if ($asArray == true) {
                return array('location' => $new, 'suffix' => $suf);
            } else {
                return $new;
            }
        }


        /**
         * returns the html between the body tags
         * if filter is set then it will return the html between the specified tags
         *
         * @param string $html
         * @param string $filter
         * @return string
         */
        public static function extractHtmlPart($html, $filter = false)
        {
            if ($filter) {
                $startTag = "<{$filter}>";
                $endTag = "</{$filter}>";
            } else {
                $startTag = "<body>";
                $endTag = "</body>";
            }
            $startPattern = ".*" . $startTag;
            $endPattern = $endTag . ".*";

            $noheader = eregi_replace($startPattern, "", $html);

            $cleanPart = eregi_replace($endPattern, "", $noheader);

            return $cleanPart;
        }

        /**
         * replaces multiple spaces with a single space
         *
         * @param string $string
         * @return string
         */
        public static function stripMultipleSpaces($string)
        {
            return trim(preg_replace('/\s+/', ' ', $string));
        }

        /**
         * note that this does not transfer any of the attributes
         *
         * @param string $tag
         * @param string $replacement
         * @param string $content
         */
        public static function replaceTag($tag, $replacement, $content, $attributes = null)
        {
            $content = preg_replace("/<{$tag}.*?>/", "<{$replacement} {$attributes}>", $content);
            $content = preg_replace("/<\/{$tag}>/", "</{$replacement}>", $content);
            return $content;
        }

    }
