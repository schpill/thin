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


        public function __construct($locale = 'fr')
        {
            $this->locale = $locale;
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

        public function translate($key, $default)
        {
            if ($this->locale != Config::get('application.language', DEFAULT_LANGUAGE)) {
                $row = jmodel('translation')->where("key = $key")->first();

                if ($row) {
                    return Html\Helper::display($row['value']);
                }
            }

            return $default;
        }

        public function getLocale()
        {
            return $this->locale;
        }
    }
