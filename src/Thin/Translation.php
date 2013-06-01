<?php
    /**
     * Translation class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Translation extends Object
    {
        public function __construct($from = 'fr', $to = 'en', $fileTranslation)
        {
            $this->setFrom($from);
            $this->setTo($to);

            if ($from != $to) {
                if (File::exists($fileTranslation)) {
                    $translations = include($fileTranslation);
                    $this->setSentences($translations);
                    Utils::set('ThinTranslate', $this);
                } else {
                    throw new Exception('The translation file does not exist.');
                }
            }
        }

        public function translate($sentence, $api = false)
        {
            $from   = $this->getFrom();
            $to     = $this->getTo();

            if ($from == $to) {
                return $sentence;
            }

            $sentences = $this->getSentences();
            if (ake($sentence, $sentences)) {
                return $sentences[$sentence];
            }

            return $sentence;
        }
    }
