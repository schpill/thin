<?php
    namespace Thin\Html;
    use Thin\Html as Html;
    use Thin\Exception as Exception;
    use Thin\Registry as Registry;
    class Doctype
    {
        /**#@+
         * DocType constants
         */
        const XHTML11             = 'XHTML11';
        const XHTML1_STRICT       = 'XHTML1_STRICT';
        const XHTML1_TRANSITIONAL = 'XHTML1_TRANSITIONAL';
        const XHTML1_FRAMESET     = 'XHTML1_FRAMESET';
        const XHTML1_RDFA         = 'XHTML1_RDFA';
        const XHTML1_RDFA11       = 'XHTML1_RDFA11';
        const XHTML_BASIC1        = 'XHTML_BASIC1';
        const XHTML5              = 'XHTML5';
        const HTML4_STRICT        = 'HTML4_STRICT';
        const HTML4_LOOSE         = 'HTML4_LOOSE';
        const HTML4_FRAMESET      = 'HTML4_FRAMESET';
        const HTML5               = 'HTML5';
        const CUSTOM_XHTML        = 'CUSTOM_XHTML';
        const CUSTOM              = 'CUSTOM';
        /**#@-*/

        /**
         * Default DocType
         * @var string
         */
        protected $_defaultDoctype = static::HTML4_LOOSE;

        /**
         * Registry containing current doctype and mappings
         * @var ArrayObject
         */
        protected $_registry;

        /**
         * Registry key in which helper is stored
         * @var string
         */
        protected $_regKey = 'Doctype';

        /**
         * Constructor
         *
         * Map constants to doctype strings, and set default doctype
         *
         * @return void
         */
        public function __construct()
        {
            if (!Registry::has($this->_regKey)) {
                $this->_registry = new \ArrayObject(array(
                    'doctypes' => array(
                        static::XHTML11             => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
                        static::XHTML1_STRICT       => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
                        static::XHTML1_TRANSITIONAL => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
                        static::XHTML1_FRAMESET     => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
                        static::XHTML1_RDFA         => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.0//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-1.dtd">',
                        static::XHTML1_RDFA11       => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.1//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-2.dtd">',
                        static::XHTML_BASIC1        => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.0//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic10.dtd">',
                        static::XHTML5              => '<!DOCTYPE html>',
                        static::HTML4_STRICT        => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
                        static::HTML4_LOOSE         => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">',
                        static::HTML4_FRAMESET      => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">',
                        static::HTML5               => '<!DOCTYPE html>',
                    )
                ));
                Registry::set($this->_regKey, $this->_registry);
                $this->setDoctype($this->_defaultDoctype);
            } else {
                $this->_registry = Registry::get($this->_regKey);
            }
        }

        /**
         * Set or retrieve doctype
         *
         * @param  string $doctype
         * @return FTV_Html_Doctype
         */
        public function doctype($doctype = null)
        {
            if (null !== $doctype) {
                switch ($doctype) {
                    case static::XHTML11:
                    case static::XHTML1_STRICT:
                    case static::XHTML1_TRANSITIONAL:
                    case static::XHTML1_FRAMESET:
                    case static::XHTML_BASIC1:
                    case static::XHTML1_RDFA:
                    case static::XHTML1_RDFA11:
                    case static::XHTML5:
                    case static::HTML4_STRICT:
                    case static::HTML4_LOOSE:
                    case static::HTML4_FRAMESET:
                    case static::HTML5:
                        $this->setDoctype($doctype);
                        break;
                    default:
                        if (substr($doctype, 0, 9) != '<!DOCTYPE') {
                            $e = new Exception('The specified doctype is malformed');
                            throw $e;
                        }
                        if (stristr($doctype, 'xhtml')) {
                            $type = static::CUSTOM_XHTML;
                        } else {
                            $type = static::CUSTOM;
                        }
                        $this->setDoctype($type);
                        $this->_registry['doctypes'][$type] = $doctype;
                        break;
                }
            }

            return $this;
        }

        /**
         * Set doctype
         *
         * @param  string $doctype
         * @return Zend_View_Helper_Doctype
         */
        public function setDoctype($doctype)
        {
            $this->_registry['doctype'] = $doctype;
            return $this;
        }

        /**
         * Retrieve doctype
         *
         * @return string
         */
        public function getDoctype()
        {
            return $this->_registry['doctype'];
        }

        /**
         * Get doctype => string mappings
         *
         * @return array
         */
        public function getDoctypes()
        {
            return $this->_registry['doctypes'];
        }

        /**
         * Is doctype XHTML?
         *
         * @return boolean
         */
        public function isXhtml()
        {
            return (stristr($this->getDoctype(), 'xhtml') ? true : false);
        }

        /**
         * Is doctype strict?
         *
         * @return boolean
         */
        public function isStrict()
        {
            switch ( $this->getDoctype() )
            {
                case static::XHTML1_STRICT:
                case static::XHTML11:
                case static::HTML4_STRICT:
                    return true;
                default:
                    return false;
            }
        }

        /**
         * Is doctype HTML5? (HeadMeta uses this for validation)
         *
         * @return booleean
         */
        public function isHtml5()
        {
            return (stristr($this->doctype(), '<!DOCTYPE html>') ? true : false);
        }

        /**
         * Is doctype RDFa?
         *
         * @return booleean
         */
        public function isRdfa()
        {
            return (stristr($this->getDoctype(), 'rdfa') ? true : false);
        }

        /**
         * String representation of doctype
         *
         * @return string
         */
        public function __toString()
        {
            $doctypes = $this->getDoctypes();
            return $doctypes[$this->getDoctype()];
        }
}
