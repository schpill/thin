<?php
    namespace Thin\Helpers;

    use Thin\Arrays;

    class Arraytoxml
    {

        private static $xml         = null;
        private static $encoding    = 'UTF-8';

        /**
         * Initialize the root XML node [optional]
         * @param $version
         * @param $encoding
         * @param $formatOutput
         */
        public static function init($version = '1.0', $encoding = 'UTF-8', $formatOutput = true)
        {
            self::$xml = new \DomDocument($version, $encoding);
            self::$xml->formatOutput = $formatOutput;
            self::$encoding = $encoding;
        }

        /**
         * Convert an Array to XML
         * @param string $nodeName - name of the root node to be converted
         * @param array $arr - aray to be converterd
         * @return DomDocument
         */
        public static function &create($nodeName, $arr = array())
        {
            $xml = self::getXmlRoot();
            $xml->appendChild(self::convert($nodeName, $arr));

            self::$xml = null;    // clear the xml node in the class for 2nd time use.

            return $xml;
        }

        /**
         * Convert an Array to XML
         * @param string $nodeName - name of the root node to be converted
         * @param array $arr - aray to be converterd
         * @return DOMNode
         */
        private static function &convert($nodeName, $arr = array())
        {
            //print_arr($nodeName);
            $xml    = self::getXmlRoot();
            $node   = $xml->createElement($nodeName);

            if (Arrays::is($arr)){
                // get the attributes first.;
                if (isset($arr['@attributes'])) {
                    foreach ($arr['@attributes'] as $key => $value) {

                        if (!self::isValidTagName($key)) {
                            throw new \Exception('[Arraytoxml] Illegal character in attribute name. attribute: ' . $key . ' in node: ' . $nodeName);
                        }

                        $node->setAttribute($key, self::boolToString($value));
                    }

                    unset($arr['@attributes']); //remove the key from the array once done.
                }

                // check if it has a value stored in @value, if yes store the value and return
                // else check if its directly stored as string
                if (isset($arr['@value'])) {
                    $node->appendChild($xml->createTextNode(self::boolToString($arr['@value'])));
                    unset($arr['@value']);    //remove the key from the array once done.
                    //return from recursion, as a note with value cannot have child nodes.

                    return $node;
                } elseif (isset($arr['@cdata'])) {
                    $node->appendChild($xml->createCDATASection(self::boolToString($arr['@cdata'])));
                    unset($arr['@cdata']);    //remove the key from the array once done.
                    //return from recursion, as a note with cdata cannot have child nodes.

                    return $node;
                }
            }

            //create subnodes using recursion
            if (Arrays::is($arr)) {
                // recurse to get the node for that key
                foreach ($arr as $key => $value) {
                    if(!self::isValidTagName($key)) {
                        throw new \Exception('[Arraytoxml] Illegal character in tag name. tag: '.$key.' in node: '.$nodeName);
                    }

                    if (Arrays::is($value) && is_numeric(key($value))) {
                        // MORE THAN ONE NODE OF ITS KIND;
                        // if the new array is numeric index, means it is array of nodes of the same kind
                        // it should follow the parent key name
                        foreach($value as $k => $v){
                            $node->appendChild(self::convert($key, $v));
                        }
                    } else {
                        // ONLY ONE NODE OF ITS KIND
                        $node->appendChild(self::convert($key, $value));
                    }

                    unset($arr[$key]); //remove the key from the array once done.
                }
            }

            // after we are done with all the keys in the array (if it is one)
            // we check if it has any text value, if yes, append it.
            if (!Arrays::is($arr)) {
                $node->appendChild($xml->createTextNode(self::boolToString($arr)));
            }

            return $node;
        }

        /*
         * Get the root XML node, if there isn't one, create it.
         */
        private static function getXmlRoot()
        {
            if (empty(self::$xml)) {
                self::init();
            }

            return self::$xml;
        }

        /*
         * Get string representation of boolean value
         */
        private static function boolToString($v)
        {
            //convert boolean to text value.
            $v = $v === true ? 'true' : $v;
            $v = $v === false ? 'false' : $v;

            return $v;
        }

        /*
         * Check if the tag name or attribute name contains illegal characters
         * Ref: http://www.w3.org/TR/xml/#sec-common-syn
         */
        private static function isValidTagName($tag)
        {
            return preg_match('/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i', $tag, $matches) && $matches[0] == $tag;
        }
    }

