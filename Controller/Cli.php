<?php
    namespace Thin\Controller;

    class Cli extends Response
    {
        /**
         * Flag; if true, when header operations are called after headers have been
         * sent, an exception will be raised; otherwise, processing will continue
         * as normal. Defaults to false.
         *
         * @see canSendHeaders()
         * @var boolean
         */
        public $headersSentThrowsException = false;


        /**
         * Magic __toString functionality
         *
         * @return string
         */
        public function __toString()
        {
            if ($this->isException() && $this->renderExceptions()) {
                $exceptions = '';

                foreach ($this->getException() as $e) {
                    $exceptions .= $e->__toString() . "\n";
                }

                return $exceptions;
            }

            return $this->_body;
        }
    }
