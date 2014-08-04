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
                static::$items = array_merge(static::$sentences, $sentences);
            } else {
                $file = APPLICATION_PATH . DS . 'translation' . DS . $file . '.php';
                if (File::exists($file)) {
                    $config = include $file;
                    static::$items = array_merge(static::$sentences, $sentences);
                }
            }
        }

        public function translate($key, $default)
        {
            return isAke(static::$sentences[$this->locale], $key, $default);
        }
    }
