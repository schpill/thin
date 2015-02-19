<?php
    namespace Thin;
    class Language
    {
        private $_config;
        private $_translation;
        private $_needTranslate = true;

        public function __construct($config)
        {
            $session    = session('web');
            $session->setLanguage($config->getLanguage());
            $this->_config = $config;
            if ($config->getLanguage() == Cms::getOption('default_language')) {
                $this->_needTranslate = false;
            } else {
                $file = APPLICATION_PATH . DS . SITE_NAME . DS . 'modules' . DS . $config->getModule() . DS . 'languages' . DS . $config->getController() . ucFirst($config->getLanguage()) . '.php';

                $this->_translation = new languageTranslation;

                if (File::exists($file)) {
                    $translation = include($file);
                    $this->_translation->populate(Arrays::exists($config->getAction(), $translation) ? $translation[$config->getAction()] : $translation);
                }
            }
        }

        public function translate($key, $default)
        {
            if (false === $this->_needTranslate) {
                return base64_decode($default);
            }
            return null !== $this->_translation->$key ? $this->_translation->$key : base64_decode($default);
        }

        public function getLanguage()
        {
            return $this->_config->getLanguage();
        }
    }
