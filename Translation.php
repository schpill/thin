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
            if (Arrays::exists($sentence, $sentences)) {
                return $sentences[$sentence];
            }

            return $sentence;
        }

        public static function auto($sentence, $source = 'fr', $target = 'en')
        {
            $key = sha1(serialize(func_get_args()));
            $res = Data::query('translation', 'key = ' . $key);
            if (count($res)) {
                $obj = current($res);
                return $obj->getSentence();
            }
            $source = Inflector::lower($source);
            $target = Inflector::lower($target);
            $url = "http://api.mymemory.translated.net/get?q=" . urlencode($sentence) . "&langpair=" . urlencode($source) . "|" . urlencode($target);

            $res = dwn($url);
            $tab = json_decode($res, true);
            if (Arrays::exists('responseData', $tab)) {
                if (Arrays::exists('translatedText', $tab['responseData'])) {
                    $translation = $tab['responseData']['translatedText'];
                    $data = array(
                        'source'        => $source,
                        'target'        => $target,
                        'key'           => $key,
                        'sentence'      => $translation,
                    );
                    Data::add('translation', $data);
                    return $translation;
                }
            }
            return $sentence;
        }
    }
