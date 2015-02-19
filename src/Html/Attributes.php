<?php
    namespace Thin\Html
    class Attributes
    {
        /**
         * Array containing the attributes list
         * @var array
         */
        private $attributes = array();

        /**
         * Add an attribute or an array thereof. If the attribute already exists, the specified values will be added to it, without overwriting the previous ones. Duplicate values are removed.
         *
         * @param string|array|\Thin\Html\Attributes $attribute The name of the attribute to add, a name-value array of attributes or an attributes object
         * @param string|array $value In case the first parametre is a string, value or array of values for the added attribute
         *
         * @return \Thin\Html\Attributes Provides a fluent interface
         */
        public function add($attribute, $value = array())
        {
            if(is_array($attribute)) {
                foreach($attribute as $k => $v) {
                    $this->add($k, $v);
                }
            } else {
                if($attribute instanceof \Thin\Html\Attributes) {
                    $this->add($attribute->getArray());
                } else {
                    $attribute = strval($attribute);
                    if(!\Thin\Arrays::exists($attribute, $this->attributes)) {
                        $this->attributes[$attribute] = array();
                    }

                    if(is_array($value)) {
                        foreach($value as $k => $v) {
                            $value[$k] = strval($v);
                        }
                    } else {
                        if(empty($value) && $value !== '0') {
                            $value = array();
                        } else {
                            $value = array(strval($value));
                        }
                    }

                    foreach($value as $v) {
                        $this->attributes[$attribute][] = $v;
                    }
                    $this->attributes[$attribute] = array_unique($this->attributes[$attribute]);
                }
            }

            return $this;
        }

        /**
         * Set the value or values of an attribute or an array thereof. Already existent attributes are overwritten.
         *
         * @param string|array|\Thin\Html\Attributes $attribute The name of the attribute to set, a name-value array of attributes or an attributes object
         * @param string|array $value In case the first parametre is a string, value or array of values for the set attribute
         *
         * @return \Thin\Html\Attributes Provides a fluent interface
         */
        public function set($attribute, $value = array())
        {
            if(is_array($attribute)) {
                $this->attributes = array();
                foreach($attribute as $k => $v) {
                    $this->set($k, $v);
                }
            } else {
                if($attribute instanceof \Thin\Html\Attributes) {
                    $this->attributes = $attribute->getArray();
                } else {
                    $attribute = strval($attribute);
                    if(is_array($value)) {
                        foreach($value as $k => $v) {
                            $value[$k] = strval($v);
                        }
                    } else {
                        if(empty($value) && $value !== '0') {
                            $value = array();
                        } else {
                            $value = array(strval($value));
                        }
                    }

                    $this->attributes[$attribute] = array_unique($value);
                }
            }

            return $this;
        }

        /**
         * Remove an attribute or a value
         *
         * @param string $attribute The attribute name to remove(or to remove a value from)
         * @param string $value The value to remove from the attribute. Omit the parametre to remove the entire attribute.
         *
         * @return \Thin\Html\Attributes Provides a fluent interface
         */
        public function remove($attribute, $value = null)
        {
            $attribute = strval($attribute);

            if(\Thin\Arrays::exists($attribute, $this->attributes)) {
                if(null === $value) {
                    unset($this->attributes[$attribute]);
                } else {
                    $value = strval($value);
                    foreach($this->attributes[$attribute] as $k => $v) {
                        if($v == $value) {
                            unset($this->attributes[$attribute][$k]);
                        }
                    }
                }
            }

            return $this;
        }

        /**
         * Get the entire attributes array or the array of values for a single attribute.
         *
         * @param string $attribute The attribute whose values are to be retrieved. Omit the parametre to fetch the entire array of attributes.
         *
         * @return array|null The attribute or attributes or null in case a nonexistent attribute is requested
         */
        public function getArray($attribute = null)
        {
            if(null === $attribute) {
                return $this->attributes;
            } else {
                $attribute = strval($attribute);
                if(ake($attribute, $this->attributes)) {
                    return $this->attributes[$attribute];
                }
            }

            return null;
        }

        /**
         * Generate the HTML code for the attributes
         *
         * @param string $attribute The attribute for which HTML code is to be generated. Omit the parametre to generate HTML code for all attributes.
         *
         * @return string HTML code
         */
        public function getHtml($attribute = null)
        {
            if(null !== $attribute) {
                $attribute = strval($attribute);
                if(\Thin\Arrays::exists($attribute, $this->attributes)) {
                    return $attribute . '="' . implode(' ', $this->attributes[$attribute]) . '"';
                }
            } else {
                $return = array();
                foreach(array_keys($this->attributes) as $attrib) {
                    $return[] = $this->getHtml($attrib);
                }
                return implode(' ', $return);
            }

            return '';
        }

        /**
         * Generate the HTML code for all attributes
         *
         * @return string HTML code
         */
        public function __toString()
        {
            return $this->getHtml();
        }

        /**
         * Check whether a given attribute or attribute value exists
         *
         * @param string $attribute Attribute name whose existence is to be checked
         * @param string $value The attribute's value to be checked. Omit the parametre to check the existence of the attribute.
         *
         * @return boolean
         */
        public function exists($attribute, $value = null)
        {
            $attribute = strval($attribute);

            if(\Thin\Arrays::exists($attribute, $this->attributes)) {
                if(null === $value || in_array(strval($value), $this->attributes[$attribute])) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Clear all attributes
         *
         * @return \Thin\Html\Attributes Provides a fluent interface
         */
        public function clear()
        {
            $this->attributes = array();
            return $this;
        }
    }
