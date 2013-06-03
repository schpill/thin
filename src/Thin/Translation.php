<?php
    /**
     * Translation class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Translation extends Object
    {
        public $sentences;

        public function __construct($language, $segment = '')
        {
            if (Inflector::length($segment)) {
                $fileTranslation = LANGUAGE_PATH . DS . ucwords(Inflector::lower(repl('.', DS, $segment))) . DS . Inflector::lower($language) . '.ini';
            } else {
                $fileTranslation = LANGUAGE_PATH . DS . Inflector::lower($language) . '.ini';
            }

            if (File::exists($fileTranslation)) {
                $ini = new Ini($fileTranslation);
                $translations = $ini->parseIni();
                $this->setSentences($translations);
                Utils::set('ThinTranslate', $this);
            } else {
                throw new Exception('The translation file ' . $fileTranslation . ' does not exist.');
            }
        }

        public function translate($sentence)
        {
            $sentences = $this->getSentences();
            if (null === $sentences) {
                return $sentence;
            }
            if (ake($sentence, $sentences)) {
                return $sentences[$sentence];
            }

            return $sentence;
        }
    }
