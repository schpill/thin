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

        public function translate($key, $default, $html = true)
        {
            if ($this->locale != Config::get('application.language', DEFAULT_LANGUAGE)) {
                $row = jmodel('translation')->where("key = $key")->where('lang = ' . $this->locale)->first();

                if ($row) {
                    if ($html) {
                        return Html\Helper::display($row['value']);
                    } else {
                        return $row['value'];
                    }
                }
            }

            return $default;
        }

        public function getLocale()
        {
            return $this->locale;
        }

        public function machine($default, $html = true)
        {
            if ($this->locale != Config::get('application.language', DEFAULT_LANGUAGE)) {
                $json = keep(function($str, $from, $to) {
                    return dwn('http://api.mymemory.translated.net/get?q=' . urlencode($str) . '&langpair=' . $from . '|' . $to);
                }, array($default, Config::get('application.language', DEFAULT_LANGUAGE), $this->locale));

                $tab = json_decode($json, true);

                $matches = isAke($josn, 'matches', false);

                if (false !== $matches) {
                    $row = Arrays::first($matches);

                    $translation = isAke($row, 'translation', $default);

                    if ($html) {
                        return Html\Helper::display($translation);
                    } else {
                        return $translation;
                    }
                }
            }

            return $default;
        }
    }
