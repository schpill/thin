<?php
    /**
     * Inflector class
     *
     * @author      Gerald Plusquellec
     */

    /**
     * Utility for modifying format of words. Change singular to plural and vice versa.
     * Under_score a CamelCased word and vice versa. Replace spaces and special characters.
     * Create a human readable word from the others. Used when consistency in naming
     * conventions must be enforced.
     */

    namespace Thin;
    class Inflector
    {

        /**
         * Contains a default map of accented and special characters to ASCII characters.  Can be
         * extended or added to using `Inflector::rules()`.
         *
         * @see FTV_Inflector::slug()
         * @see FTV_Inflector::rules()
         * @var array
         */
        protected static $_transliteration = array(
            '/à|á|å|â/' => 'a',
            '/è|é|ê|ẽ|ë/' => 'e',
            '/ì|í|î/' => 'i',
            '/ò|ó|ô|ø/' => 'o',
            '/ù|ú|ů|û/' => 'u',
            '/ç/' => 'c', '/ñ/' => 'n',
            '/ä|æ/' => 'ae', '/ö/' => 'oe',
            '/ü/' => 'ue', '/Ä/' => 'Ae',
            '/Ü/' => 'Ue', '/Ö/' => 'Oe',
            '/ß/' => 'ss'
        );

        /**
         * Indexed array of words which are the same in both singular and plural form.  You can add
         * rules to this list using `Inflector::rules()`.
         *
         * @see FTV_Inflector::rules()
         * @var array
         */
        protected static $_uninflected = array(
            'Amoyese', 'bison', 'Borghese', 'bream', 'breeches', 'britches', 'buffalo', 'cantus',
            'carp', 'chassis', 'clippers', 'cod', 'coitus', 'Congoese', 'contretemps', 'corps',
            'debris', 'diabetes', 'djinn', 'eland', 'elk', 'equipment', 'Faroese', 'flounder',
            'Foochowese', 'gallows', 'Genevese', 'Genoese', 'Gilbertese', 'graffiti',
            'headquarters', 'herpes', 'hijinks', 'Hottentotese', 'information', 'innings',
            'jackanapes', 'Kiplingese', 'Kongoese', 'Lucchese', 'mackerel', 'Maltese', 'media',
            'mews', 'moose', 'mumps', 'Nankingese', 'news', 'nexus', 'Niasese', 'People',
            'Pekingese', 'Piedmontese', 'pincers', 'Pistoiese', 'pliers', 'Portuguese',
            'proceedings', 'rabies', 'rice', 'rhinoceros', 'salmon', 'Sarawakese', 'scissors',
            'sea[- ]bass', 'series', 'Shavese', 'shears', 'siemens', 'species', 'swine', 'testes',
            'trousers', 'trout','tuna', 'Vermontese', 'Wenchowese', 'whiting', 'wildebeest',
            'Yengeese'
        );

        /**
         * Contains the list of pluralization rules.
         *
         * @see FTV_Inflector::rules()
         * @var array Contains the following keys:
         *   - `'rules'`: An array of regular expression rules in the form of `'match' => 'replace'`,
         *     which specify the matching and replacing rules for the pluralization of words.
         *   - `'uninflected'`: A indexed array containing regex word patterns which do not get
         *     inflected (i.e. singular and plural are the same).
         *   - `'irregular'`: Contains key-value pairs of specific words which are not inflected
         *     according to the rules. This is populated from `Inflector::$_plural` when the class
         *     is loaded.
         */
        protected static $_singular = array(
            'rules' => array(
                '/(s)tatuses$/i' => '\1\2tatus',
                '/^(.*)(menu)s$/i' => '\1\2',
                '/(quiz)zes$/i' => '\\1',
                '/(matr)ices$/i' => '\1ix',
                '/(vert|ind)ices$/i' => '\1ex',
                '/^(ox)en/i' => '\1',
                '/(alias)(es)*$/i' => '\1',
                '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|viri?)i$/i' => '\1us',
                '/(cris|ax|test)es$/i' => '\1is',
                '/(shoe)s$/i' => '\1',
                '/(o)es$/i' => '\1',
                '/ouses$/' => 'ouse',
                '/uses$/' => 'us',
                '/([m|l])ice$/i' => '\1ouse',
                '/(x|ch|ss|sh)es$/i' => '\1',
                '/(m)ovies$/i' => '\1\2ovie',
                '/(s)eries$/i' => '\1\2eries',
                '/([^aeiouy]|qu)ies$/i' => '\1y',
                '/([lr])ves$/i' => '\1f',
                '/(tive)s$/i' => '\1',
                '/(hive)s$/i' => '\1',
                '/(drive)s$/i' => '\1',
                '/([^fo])ves$/i' => '\1fe',
                '/(^analy)ses$/i' => '\1sis',
                '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
                '/([ti])a$/i' => '\1um',
                '/(p)eople$/i' => '\1\2erson',
                '/(m)en$/i' => '\1an',
                '/(c)hildren$/i' => '\1\2hild',
                '/(n)ews$/i' => '\1\2ews',
                '/^(.*us)$/' => '\\1',
                '/s$/i' => ''
            ),
            'irregular' => array(),
            'uninflected' => array(
                '.*[nrlm]ese', '.*deer', '.*fish', '.*measles', '.*ois', '.*pox', '.*sheep', '.*ss'
            )
        );

        /**
         * Contains a cache map of previously singularized words.
         *
         * @var array
         */
        protected static $_singularized = array();

        /**
         * Contains the list of pluralization rules.
         *
         * @see FTV_Inflector::rules()
         * @var array Contains the following keys:
         *   - `'rules'`: An array of regular expression rules in the form of `'match' => 'replace'`,
         *     which specify the matching and replacing rules for the pluralization of words.
         *   - `'uninflected'`: A indexed array containing regex word patterns which do not get
         *     inflected (i.e. singular and plural are the same).
         *   - `'irregular'`: Contains key-value pairs of specific words which are not inflected
         *     according to the rules.
         */
        protected static $_plural = array(
            'rules' => array(
                '/(s)tatus$/i' => '\1\2tatuses',
                '/(quiz)$/i' => '\1zes',
                '/^(ox)$/i' => '\1\2en',
                '/([m|l])ouse$/i' => '\1ice',
                '/(matr|vert|ind)(ix|ex)$/i'  => '\1ices',
                '/(x|ch|ss|sh)$/i' => '\1es',
                '/([^aeiouy]|qu)y$/i' => '\1ies',
                '/(hive)$/i' => '\1s',
                '/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
                '/sis$/i' => 'ses',
                '/([ti])um$/i' => '\1a',
                '/(p)erson$/i' => '\1eople',
                '/(m)an$/i' => '\1en',
                '/(c)hild$/i' => '\1hildren',
                '/(buffal|tomat)o$/i' => '\1\2oes',
                '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|vir)us$/i' => '\1i',
                '/us$/' => 'uses',
                '/(alias)$/i' => '\1es',
                '/(ax|cri|test)is$/i' => '\1es',
                '/s$/' => 's',
                '/^$/' => '',
                '/$/' => 's'
            ),
            'irregular' => array(
                'atlas' => 'atlases', 'beef' => 'beefs', 'brother' => 'brothers',
                'child' => 'children', 'corpus' => 'corpuses', 'cow' => 'cows',
                'ganglion' => 'ganglions', 'genie' => 'genies', 'genus' => 'genera',
                'graffito' => 'graffiti', 'hoof' => 'hoofs', 'loaf' => 'loaves', 'man' => 'men',
                'leaf' => 'leaves', 'money' => 'monies', 'mongoose' => 'mongooses', 'move' => 'moves',
                'mythos' => 'mythoi', 'numen' => 'numina', 'occiput' => 'occiputs',
                'octopus' => 'octopuses', 'opus' => 'opuses', 'ox' => 'oxen', 'penis' => 'penises',
                'person' => 'people', 'sex' => 'sexes', 'soliloquy' => 'soliloquies',
                'testis' => 'testes', 'trilby' => 'trilbys', 'turf' => 'turfs'
            ),
            'uninflected' => array(
                '.*[nrlm]ese', '.*deer', '.*fish', '.*measles', '.*ois', '.*pox', '.*sheep'
            )
        );

        /**
         * Contains a cache map of previously pluralized words.
         *
         * @var array
         */
        protected static $_pluralized = array();

        /**
         * Contains a cache map of previously camelized words.
         *
         * @var array
         */
        protected static $_camelized = array();

        /**
         * Contains a cache map of previously underscored words.
         *
         * @var array
         */
        protected static $_underscored = array();

        /**
         * Contains a cache map of previously humanized words.
         *
         * @var array
         */
        protected static $_humanized = array();

        public static function utf8($str)
        {
            if (false === FTV_Utils::isUtf8($str)) {
                $str = utf8_encode($str);
            }
            return $str;
        }

        /**
         * Gets or adds inflection and transliteration rules.
         *
         * @param string $type Either `'transliteration'`, `'uninflected'`, `'singular'` or `'plural'`.
         * @param array $config
         * @return mixed If `$config` is empty, returns the rules list specified
         *         by `$type`, otherwise returns `null`.
         */
        public static function rules($type, $config = array())
        {
            $var = '_' . $type;

            if (!isset(static::${$var})) {
                return null;
            }
            if (empty($config)) {
                return static::${$var};
            }
            switch ($type) {
                case 'transliteration':
                    $_config = array();

                    foreach ($config as $key => $val) {
                        if ($key[0] != '/') {
                            $key = '/' . join('|', array_filter(preg_split('//u', $key))) . '/';
                        }
                        $_config[$key] = $val;
                    }
                    static::$_transliteration = array_merge(
                        $_config, static::$_transliteration, $_config
                    );
                break;
                case 'uninflected':
                    static::$_uninflected = array_merge(static::$_uninflected, (array) $config);
                    static::$_plural['regexUninflected'] = null;
                    static::$_singular['regexUninflected'] = null;

                    foreach ((array) $config as $word) {
                        unset(static::$_singularized[$word], static::$_pluralized[$word]);
                    }
                break;
                case 'singular':
                case 'plural':
                    if (isset(static::${$var}[key($config)])) {
                        foreach ($config as $rType => $set) {
                            static::${$var}[$rType] = array_merge($set, static::${$var}[$rType], $set);

                            if ($rType == 'irregular') {
                                $swap = ($type == 'singular' ? '_plural' : '_singular');
                                static::${$swap}[$rType] = array_flip(static::${$var}[$rType]);
                            }
                        }
                    } else {
                        static::${$var}['rules'] = array_merge(
                            $config, static::${$var}['rules'], $config
                        );
                    }
                break;
            }
        }

        /**
         * Changes the form of a word from singular to plural.
         *
         * @param string $word Word in singular form.
         * @return string Word in plural form.
         */
        public static function pluralize($word)
        {
            if (isset(static::$_pluralized[$word])) {
                return static::$_pluralized[$word];
            }
            extract(static::$_plural);

            if (!isset($regexUninflected) || !isset($regexIrregular)) {
                $regexUninflected = static::_enclose(join( '|', $uninflected + static::$_uninflected));
                $regexIrregular = static::_enclose(join( '|', array_keys($irregular)));
                static::$_plural += compact('regexUninflected', 'regexIrregular');
            }
            if (preg_match('/(' . $regexUninflected . ')$/i', $word, $regs)) {
                return static::$_pluralized[$word] = $word;
            }
            if (preg_match('/(.*)\\b(' . $regexIrregular . ')$/i', $word, $regs)) {
                $plural = substr($word, 0, 1) . substr($irregular[static::lower($regs[2])], 1);
                return static::$_pluralized[$word] = $regs[1] . $plural;
            }
            foreach ($rules as $rule => $replacement) {
                if (preg_match($rule, $word)) {
                    return static::$_pluralized[$word] = preg_replace($rule, $replacement, $word);
                }
            }
            return static::$_pluralized[$word] = $word;
        }

        /**
         * Changes the form of a word from plural to singular.
         *
         * @param string $word Word in plural form.
         * @return string Word in singular form.
         */
        public static function singularize($word)
        {
            if (isset(static::$_singularized[$word])) {
                return static::$_singularized[$word];
            }
            if (empty(static::$_singular['irregular'])) {
                static::$_singular['irregular'] = array_flip(static::$_plural['irregular']);
            }
            extract(static::$_singular);

            if (!isset($regexUninflected) || !isset($regexIrregular)) {
                $regexUninflected = static::_enclose(join('|', $uninflected + static::$_uninflected));
                $regexIrregular = static::_enclose(join('|', array_keys($irregular)));
                static::$_singular += compact('regexUninflected', 'regexIrregular');
            }
            if (preg_match("/(.*)\\b({$regexIrregular})\$/i", $word, $regs)) {
                $singular = substr($word, 0, 1) . substr($irregular[strtolower($regs[2])], 1);
                return static::$_singularized[$word] = $regs[1] . $singular;
            }
            if (preg_match('/^(' . $regexUninflected . ')$/i', $word, $regs)) {
                return static::$_singularized[$word] = $word;
            }
            foreach ($rules as $rule => $replacement) {
                if (preg_match($rule, $word)) {
                    return static::$_singularized[$word] = preg_replace($rule, $replacement, $word);
                }
            }
            return static::$_singularized[$word] = $word;
        }

        /**
         * Clears local in-memory caches.  Can be used to force a full-cache clear when updating
         * inflection rules mid-way through request execution.
         *
         * @return void
         */
        public static function reset()
        {
            static::$_singularized = static::$_pluralized = array();
            static::$_camelized = static::$_underscored = array();
            static::$_humanized = array();

            static::$_plural['regexUninflected'] = static::$_singular['regexUninflected'] = null;
            static::$_plural['regexIrregular'] = static::$_singular['regexIrregular'] = null;
            static::$_transliteration = array(
                '/à|á|å|â/' => 'a', '/è|é|ê|ẽ|ë/' => 'e',
                '/ì|í|î/' => 'i', '/ò|ó|ô|ø/' => 'o',
                '/ù|ú|ů|û/' => 'u', '/ç/' => 'c',
                '/ñ/' => 'n', '/ä|æ/' => 'ae', '/ö/' => 'oe',
                '/ü/' => 'ue', '/Ä/' => 'Ae',
                '/Ü/' => 'Ue', '/Ö/' => 'Oe',
                '/ß/' => 'ss'
            );
        }

        /**
         * Takes a under_scored word and turns it into a CamelCased or camelBack word
         *
         * @param string $word An under_scored or slugged word (i.e. `'red_bike'` or `'red-bike'`).
         * @param boolean $cased If false, first character is not upper cased
         * @return string CamelCased version of the word (i.e. `'RedBike'`).
         */
        public static function camelize($string, $spacify = true, $lazy = false)
        {
            if($spacify) {
                $repl = "str_replace('_', ' ', strtoupper('\\1'))";
            } else {
                $repl = "strtoupper('\\2')";
            }
            $ret = preg_replace("`(?<=[a-z0-9])(_([a-z0-9]))`e", $repl, $string);
            $ret = str_replace(' ', '', $ret);
            if($lazy) {
                return lcfirst($ret);
            } else {
                return ucfirst($ret);
            }
        }

        public static function uncamelize($string, $splitter = "_")
        {
            $string = preg_replace('/(?!^)[[:upper:]][[:lower:]]/', '$0', preg_replace('/(?!^)[[:upper:]]+/', $splitter . '$0', $string));
            return static::lower($string);
        }

        /**
         * Takes a CamelCased version of a word and turns it into an under_scored one.
         *
         * @param string $word CamelCased version of a word (i.e. `'RedBike'`).
         * @return string Under_scored version of the workd (i.e. `'red_bike'`).
         */
        public static function underscore($word)
        {
            if (isset(static::$_underscored[$word])) {
                return static::$_underscored[$word];
            }
            return static::$_underscored[$word] = static::lower(static::slug($word, '_'));
        }

        /**
         * Returns a string with all spaces converted to given replacement and
         * non word characters removed.  Maps special characters to ASCII using
         * `Inflector::$_transliteration`, which can be updated using `Inflector::rules()`.
         *
         * @see FTV_Inflector::rules()
         * @param string $string An arbitrary string to convert.
         * @param string $replacement The replacement to use for spaces.
         * @return string The converted string.
         */
        public static function slug($string, $replacement = '-')
        {
            $map = static::$_transliteration + array(
                '/[^\w\s]/' => ' ', '/\\s+/' => $replacement,
                '/(?<=[a-z])([A-Z])/' => $replacement . '\\1',
                str_replace(':rep', preg_quote($replacement, '/'), '/^[:rep]+|[:rep]+$/') => ''
            );
            return preg_replace(array_keys($map), array_values($map), $string);
        }

        /**
         * Takes an under_scored version of a word and turns it into an human- readable form
         * by replacing underscores with a space, and by upper casing the initial character.
         *
         * @param string $word Under_scored version of a word (i.e. `'red_bike'`).
         * @param string $separator The separator character used in the initial string.
         * @return string Human readable version of the word (i.e. `'Red Bike'`).
         */
        public static function humanize($word, $separator = '_')
        {
            if (isset(static::$_humanized[$key = $word . ':' . $separator])) {
                return static::$_humanized[$key];
            }
            return static::$_humanized[$key] = ucwords(repl($separator, " ", $word));
        }

        /**
         * Takes a CamelCased class name and returns corresponding under_scored table name.
         *
         * @param string $className CamelCased class name (i.e. `'Post'`).
         * @return string Under_scored and plural table name (i.e. `'posts'`).
         */
        public static function tableize($className)
        {
            return static::pluralize(static::underscore($className));
        }

        /**
         * Takes a under_scored table name and returns corresponding class name.
         *
         * @param string $tableName Under_scored and plural table name (i.e. `'posts'`).
         * @return string CamelCased class name (i.e. `'Post'`).
         */
        public static function reclassify($tableName)
        {
            return static::camelize(static::singularize($tableName));
        }

        /**
         * Enclose a string for preg matching.
         *
         * @param string $string String to enclose
         * @return string Enclosed string
         */
        protected static function _enclose($string)
        {
            return '(?:' . $string . ')';
        }

        public static function length($value)
        {
            return (MB_STRING) ? mb_strlen($value, static::encoding()) : strlen($value);
        }

        public static function lower($value)
        {
            return (MB_STRING) ? mb_strtolower($value, static::encoding()) : strtolower($value);
        }

        public static function upper($value)
        {
            return (MB_STRING) ? mb_strtoupper($value, static::encoding()) : strtoupper($value);
        }

        public static function substr($str, $start, $length = false, $encoding = 'utf-8')
        {
            if (is_array($str)) {
                return false;
            }
            if (function_exists('mb_substr')) {
                return mb_substr($str, (int)$start, ($length === false ? static::length($str) : (int)$length), $encoding);
            }
            return substr($str, $start, ($length === false ? static::length($str) : (int)$length));
        }

        public static function ucfirst($str)
        {
            return static::upper(static::substr($str, 0, 1)) . static::substr($str, 1);
        }

        public static function isEmpty($value)
        {
            return ($value === '' || $value === null);
        }

        public static function encoding()
        {
            return 'utf-8';
        }

        public static function urlize($text)
        {
            // Remove all non url friendly characters with the unaccent function
            $text = static::lower(static::unaccent($text));

            // Remove all none word characters
            $text = preg_replace('/\W/', ' ', $text);

            // More stripping. Replace spaces with dashes
            $text = static::lower(preg_replace('/[^A-Z^a-z^0-9^\/]+/', '-',
                               preg_replace('/([a-z\d])([A-Z])/', '\1_\2',
                               preg_replace('/([A-Z]+)([A-Z][a-z])/', '\1_\2',
                               preg_replace('/::/', '/', $text)))));

            return trim($text, '-');
        }

        public static function unaccent($string)
        {
            if (!preg_match('/[\x80-\xff]/', $string)) {
                return $string;
            }

            if (Utils::isUtf8($string)) {
                $chars = array(
                // Decompositions for Latin-1 Supplement
                chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
                chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
                chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
                chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
                chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
                chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
                chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
                chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
                chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
                chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
                chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
                chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
                chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
                chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
                chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
                chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
                chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
                chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
                chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
                chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
                chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
                chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
                chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
                chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
                chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
                chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
                chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
                chr(195).chr(191) => 'y',
                // Decompositions for Latin Extended-A
                chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
                chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
                chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
                chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
                chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
                chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
                chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
                chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
                chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
                chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
                chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
                chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
                chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
                chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
                chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
                chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
                chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
                chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
                chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
                chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
                chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
                chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
                chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
                chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
                chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
                chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
                chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
                chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
                chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
                chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
                chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
                chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
                chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
                chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
                chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
                chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
                chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
                chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
                chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
                chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
                chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
                chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
                chr(197).chr(148) => 'R', chr(197).chr(149) => 'r',
                chr(197).chr(150) => 'R', chr(197).chr(151) => 'r',
                chr(197).chr(152) => 'R', chr(197).chr(153) => 'r',
                chr(197).chr(154) => 'S', chr(197).chr(155) => 's',
                chr(197).chr(156) => 'S', chr(197).chr(157) => 's',
                chr(197).chr(158) => 'S', chr(197).chr(159) => 's',
                chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
                chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
                chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
                chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
                chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
                chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
                chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
                chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
                chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
                chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
                chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
                chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
                chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
                chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
                chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
                chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
                // Euro Sign
                chr(226).chr(130).chr(172) => 'E',
                // GBP (Pound) Sign
                chr(194).chr(163) => '',
                'Ã„' => 'Ae', 'Ã¤' => 'ae', 'Ãœ' => 'Ue', 'Ã¼' => 'ue',
                'Ã–' => 'Oe', 'Ã¶' => 'oe', 'ÃŸ' => 'ss');

                $string = strtr($string, $chars);
            } else {
                // Assume ISO-8859-1 if not UTF-8
                $chars['in'] = chr(128).chr(131).chr(138).chr(142).chr(154).chr(158)
                    .chr(159).chr(162).chr(165).chr(181).chr(192).chr(193).chr(194)
                    .chr(195).chr(196).chr(197).chr(199).chr(200).chr(201).chr(202)
                    .chr(203).chr(204).chr(205).chr(206).chr(207).chr(209).chr(210)
                    .chr(211).chr(212).chr(213).chr(214).chr(216).chr(217).chr(218)
                    .chr(219).chr(220).chr(221).chr(224).chr(225).chr(226).chr(227)
                    .chr(228).chr(229).chr(231).chr(232).chr(233).chr(234).chr(235)
                    .chr(236).chr(237).chr(238).chr(239).chr(241).chr(242).chr(243)
                    .chr(244).chr(245).chr(246).chr(248).chr(249).chr(250).chr(251)
                    .chr(252).chr(253).chr(255);

                $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

                $string = strtr($string, $chars['in'], $chars['out']);
                $doubleChars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
                $doubleChars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
                $string = str_replace($doubleChars['in'], $doubleChars['out'], $string);
            }

            return $string;
        }

        public static function urlSafeB64Encode($data)
        {
            $b64 = base64_encode($data);
            $b64 = str_replace(array('+', '/', '\r', '\n', '='), array('-', '_'),  $b64);
            return $b64;
        }

        public static function urlSafeB64Decode($b64)
        {
            $b64 = str_replace(array('-', '_'), array('+', '/'), $b64);
            return base64_decode($b64);
        }

        /**
        * Takes the namespace off the given class name.
        *
        * @param string the class name
        * @return string the string without the namespace
        */
        public static function deNamespace($className)
        {
            $className = trim($className, '\\');
            if ($lastSeparator = strrpos($className, '\\')) {
                $className = substr($className, $lastSeparator + 1);
            }
            return $className;
        }

        /**
        * Returns the namespace of the given class name.
        *
        * @param string $class_name the class name
        * @return string the string without the namespace
        */
        public static function getNamespace($className)
        {
            $className = trim($className, '\\');
            if ($lastSeparator = strrpos($className, '\\')) {
                return substr($className, 0, $lastSeparator + 1);
            }
            return '';
        }

        public static function getFileExtension($filename, $lower = true)
        {
            if (true === $lower) {
                $filename = static::lower($filename);
            }
            $tab = explode('.', $filename);
            return end($tab);
        }

        public static function strReplaceFirst($search, $replace, $subject)
        {
            return implode($replace, explode($search, $subject, 2));
        }

        /**
         * Limit the number of characters in a string.
         *
         * <code>
         *      // Returns "hel..."
         *      echo Inflector::limit('hello word', 3);
         *
         *      // Limit the number of characters and append a custom ending
         *      echo Inflector::limit('hello word', 3, '---');
         * </code>
         *
         * @param  string  $value
         * @param  int     $limit
         * @param  string  $end
         * @return string
         */
        public static function limit($value, $limit = 100, $end = '...')
        {
            if (static::length($value) <= $limit) {
                return $value;
            }
            return mb_substr($value, 0, $limit, 'utf8') . $end;
        }

        /**
         * Limit the number of chracters in a string including custom ending
         *
         * <code>
         *      // Returns "hello..."
         *      echo Inflector::limitExact('hello word', 9);
         *
         *      // Limit the number of characters and append a custom ending
         *      echo Inflector::limitExact('hello word', 9, '---');
         * </code>
         *
         * @param  string  $value
         * @param  int     $limit
         * @param  string  $end
         * @return string
         */
        public static function limitExact($value, $limit = 100, $end = '...')
        {
            if (static::length($value) <= $limit) {
                return $value;
            }

            $limit -= static::length($end);

            return static::limit($value, $limit, $end);
        }

        /**
         * Convert a string to title case (ucwords equivalent).
         *
         * <code>
         *      // Convert a string to title case
         *      $title = Inflector::title('hello word');
         *
         *      // Convert a multi-byte string to title case
         *      $title = Inflector::title('hélène de Troie');
         * </code>
         *
         * @param  string  $value
         * @return string
         */
        public static function title($value)
        {
            return mb_convert_case($value, MB_CASE_TITLE, 'utf8');
        }

        /**
         * Limit the number of words in a string.
         *
         * <code>
         *      // Returns "This is a..."
         *      echo Inflector::words('This is a sentence.', 3);
         *
         *      // Limit the number of words and append a custom ending
         *      echo Inflector::words('This is a sentence.', 3, '---');
         * </code>
         *
         * @param  string  $value
         * @param  int     $words
         * @param  string  $end
         * @return string
         */
        public static function words($value, $words = 100, $end = '...')
        {
            if (trim($value) == '') {
                return '';
            }

            preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

            if (static::length($value) == static::length($matches[0])) {
                $end = '';
            }

            return rtrim($matches[0]) . $end;
        }

        /**
         * Generate a URL friendly "slug" from a given string.
         *
         * <code>
         *      // Returns "this-is-my-blog-post"
         *      $slug = Inflector::slugify('This is my blog post!');
         *
         *      // Returns "this_is_my_blog_post"
         *      $slug = Inflector::slugify('This is my blog post!', '_');
         * </code>
         *
         * @param  string  $title
         * @param  string  $separator
         * @return string
         */
        public static function slugify($title, $separator = '-')
        {
            $title = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $title);

            // Remove all characters that are not the separator, letters, numbers, or whitespace.
            $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', static::lower($title));

            // Replace all separator characters and whitespace by a single separator
            $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

            return trim($title, $separator);
        }

        /**
         * Convert a string to an underscored, camel-cased class name.
         *
         * This method is primarily used to format task and controller names.
         *
         * <code>
         *      // Returns "Task_Name"
         *      $class = Inflector::classify('task_name');
         *
         *      // Returns "Hello_Word"
         *      $class = Inflector::classify('taylor otwell')
         * </code>
         *
         * @param  string  $value
         * @return string
         */
        public static function classify($value)
        {
            $search = array('_', '-', '.');

            return repl(' ', '_', static::title(repl($search, ' ', $value)));
        }

        /**
         * Return the "URI" style segments in a given string.
         *
         * @param  string  $value
         * @return array
         */
        public static function segments($value)
        {
            return array_diff(explode('/', trim($value, '/')), array(''));
        }

        /**
         * Generate a random alpha or alpha-numeric string.
         *
         * <code>
         *      // Generate a 40 character random alpha-numeric string
         *      echo Inflector::random(40);
         *
         *      // Generate a 16 character random alphabetic string
         *      echo Inflector::random(16, 'alpha');
         * <code>
         *
         * @param  int     $length
         * @param  string  $type
         * @return string
         */
        public static function random($length, $type = 'alnum')
        {
            return substr(str_shuffle(str_repeat(static::pool($type), 5)), 0, $length);
        }

        /**
         * Determine if a given string matches a given pattern.
         *
         * @param  string  $pattern
         * @param  string  $value
         * @return bool
         */
        public static function is($pattern, $value)
        {
            // Asterisks are translated into zero-or-more regular expression wildcards
            // to make it convenient to check if the URI starts with a given pattern
            // such as "library/*". This is only done when not root.
            if ($pattern !== '/') {
                $pattern = repl('*', '(.*)', $pattern) . '\z';
            } else {
                $pattern = '^/$';
            }

            return preg_match('#' . $pattern . '#', $value);
        }


        /**
         * Get the character pool for a given type of random string.
         *
         * @param  string  $type
         * @return string
         */
        protected static function pool($type)
        {
            switch ($type) {
                case 'alpha':
                    return 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                case 'alnum':
                    return '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                default:
                    throw new Exception("Invalid random string type [$type].");
            }
        }
    }
