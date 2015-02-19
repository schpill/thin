<?php
    namespace Thin\Filter;

    class Striptags extends \Thin\Filter
    {
        /**
         * Whether comments should be left in
         * @var boolean
         */
        private $allowComments = false;

        /**
         * An array of HTML tags that are not stripped
         * @var array
         */
        private $allowedTags = array();

        /**
         * An array of HTML attributes that are not stripped
         * @var array
         */
        private $allowedAttributes = array();

        /**
         * Strip the undesired HTML markup
         *
         * @param string $value The input string with HTML markup
         *
         * @return string Filtered string
         *
         *
         */
        public function filter($value)
        {
            // start by stripping the comments, if necessary
            if(!$this->allowComments) {
                $value = preg_replace('/<!\-\-.*\-\->/U', '', $value);
            }

            // strip unallowed tags
            $allowed = '';
            foreach($this->allowedTags as $tag) {
                $allowed .= "<{$tag}>";
            }
            $value = strip_tags($value, $allowed);

            // strip unallowed attributes - only if there are allowed tags,
            // otherwise all attributes have already been removed with the tags
            if(!empty($this->allowedTags)) {
                $allowed = $this->allowedAttributes;
                do {
                    $old   = $value;
                    $value = preg_replace_callback('/<[a-zA-Z]+ *(([a-zA-Z_:][\-a-zA-Z0-9_:\.]+) *=.*["\'].*["\']).*>/U', function($matches) use($allowed) {
                        if(in_array($matches[2], $allowed)) {
                            return $matches[0];
                        } else {
                            return repl(' ' . $matches[1], '', $matches[0]);
                        }
                    }, $value);
                } while($old != $value);
            }

            // we're left with the filtered value
            return $value;
        }

        /**
         * Set the HTML tags that should be left in the input string
         *
         * @param array|string $tags The allowed tags: either an array or a string with a single tag.
         *
         * @return \Thin\Filter\StripTags Provides a fluent interface
         *
         *
         */
        public function setAllowedTags($tags)
        {
            if(!is_array($tags)) {
                $tags = [$tags];
            }
            $this->allowedTags = $tags;
            return $this;
        }

        /**
         * Set the HTML attributes that should be left in the unstripped tags in the input string
         *
         * @param array $attributes The allowed attributes: either an array or a string with a single attribute.
         *
         * @return \Thin\Filter\StripTags Provides a fluent interface
         *
         *
         */
        public function setAllowedAttributes($attributes)
        {
            if(!is_array($attributes)) {
                $attributes = [$attributes];
            }
            $this->allowedAttributes = $attributes;
            return $this;
        }

        /**
         * Choose whether HTML comments should be left in or stripped.
         *
         * By default, the filter removes HTML comments, so there's no need to explicitly set this option to false.
         *
         * @param boolean $flag True or omit to leave the comments intact, false otherwise.
         *
         * @return \Thin\Filter\StripTags Provides a fluent interface
         *
         *
         */
        public function setAllowComments($flag = true)
        {
            $this->allowComments =(boolean)$flag;
            return $this;
        }
    }
