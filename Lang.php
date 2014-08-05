<?php
    namespace Thin;

    class Lang
    {
        /**.
         * The lang locale.
         *
         * @var string
         */
        private $locale;

        /**
         * All of the loaded sentences.
         *
         * The lang arrays are keyed.
         *
         * @var array
         */
        public static $sentences = array();

        public function __construct($locale = 'fr')
        {
            $this->locale = $locale;
            static::$sentences = include APPLICATION_PATH . DS. 'translation' .DS  . 'core.php';
        }

        public static function instance($locale = 'fr')
        {
            $key    = sha1(serialize(func_get_args()));
            $has    = Instance::has('Lang', $key);
            if (true === $has) {
                return Instance::get('Lang', $key);
            } else {
                return Instance::make('Lang', $key, with(new self($app)));
            }
        }

        public static function load($file)
        {
            if (File::exists($file)) {
                $sentences = include $file;
                static::$sentences = array_merge(static::$sentences, $sentences);
            } else {
                $file = APPLICATION_PATH . DS . 'translation' . DS . $file . '.php';
                if (File::exists($file)) {
                    $config = include $file;
                    static::$sentences = array_merge(static::$sentences, $sentences);
                }
            }
        }

        public static function addSentence($sentence, $language = null)
        {
            $language = is_null($language) ? $this->locale : $language;
            $segment = isAke(static::$sentences, $language);
            $segment = array_merge($segment, $sentence);
            static::$sentences = array_merge(static::$sentences, $segment);
        }

        public function translate($key, $default)
        {
            return $this->locale != Config::get('application.language', DEFAULT_LANGUAGE)
            ? isAke(static::$sentences[$this->locale], $key, $default)
            : $default;
        }

        public function getLocale()
        {
            return $this->locale;
        }
    }
