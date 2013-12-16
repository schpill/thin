<?php
    /**
     * Cell class
     * @author      Gerald Plusquellec
     */
    namespace Thin\Html;
    use Thin\Html as Html;
    class Cell
    {
        /**
         * The cell value
         *
         * @var string
         */
        protected $_value;

        /**
         * The cell attributes map
         *
         * @var array
         */
        protected $_attributes = array();

        /**
         * @var boolean
         */
        protected $_isHeader = false;

        /**
         * @param array $attributes
         */
        public function setAttributes(array $attributes = array())
        {
            $this->_attributes = $attributes;
        }

        /**
         * @return array
         */
        public function getAttributes()
        {
            return $this->_attributes;
        }

        /**
         * @param string $value
         */
        public function setValue($value)
        {
            $this->_value = $value;
        }

        /**
         * @return string
         */
        public function getValue()
        {
            return $this->_value;
        }

        /**
         * @return boolean
         */
        public function isHeader()
        {
            $this->_isHeader = true;
        }

        /**
         * Generates a new table cell
         *
         * @param string $value
         * @param array $attributes
         *
         * @return FTV_Html_Cell
         */
        public function cell($value, array $attributes = null)
        {
            $cell = new self;
            $cell->setValue($value);

            if (null !== $attributes) {
                $cell->setAttributes($attributes);
            }

            return $cell;
        }

        /**
         * Renders the table cell
         *
         * @return string
         */
        public function __toString()
        {
            $tag = 't' . ($this->_isHeader ? 'h' : 'd');
            return sprintf('<%s%s>%s</%s>', $tag, Html::attributes($this->getAttributes()), Html::escape($this->getValue()), $tag);
        }
    }

