<?php
    /**
     * Phonetic class
     *
     * TODO add other languages / This class can make phonemes and analyze similarity from french (by default) and english.
     *
     * @author      Gerald Plusquellec
     */

    namespace Thin;
    class Phonetic
    {
        private $_tolerance;
        private $_sort = true;

        public function __construct($tolerance = 0.2, $sort = true)
        {
            if (1 < $tolerance) {
                $tolerance = round(($tolerance / 100), 2);
            }
            $this->setTolerance($tolerance);
            $this->setSort($sort);
        }


        /**
        * Set fault tolerance for what is considered 'similar'.
        *
        * @param float $tol a percentage setting how close the strings need to be to be considered similar.
        **/
        public function setTolerance($tolerance = 0.20)
        {
            if($tolerance < 0 || $tolerance > 1) {
                return false;
            }
            $this->_tolerance = round($tolerance, 2);
        }


        /**
        * Set whether the strings to compare should be sorted alphabetically.
        *
        * @param bool $sort true sorts the strings, false doesnt.
        **/
        public function setSort($sort = true)
        {
            $this->_sort = $sort;
        }


        /**
        * Get sorted boolean, whether the strings to compare should be sorted alphabetically.
        *
        * @return bool
        **/
        public function getSort()
        {
            return $this->_sort;
        }


        /**
        * Get default tolerance for what is considered 'similar'.
        *
        * @return float
        **/
        public function getTolerance()
        {
            return $this->_tolerance;
        }


        /**
        * Compare 2 strings to see how similar they are.
        *
        * @param string $string The first string to compare. Max length of 255 chars.
        * @param string $cmp The second string to comapre against the first. Max length of 255 chars.
        * @param string $language The language to make phonemes
        * @return mixed false if $string or $cmp is empty or longer then 255 chars, the max length for a PHP levenshtein.
        **/
        public function similarity($string = null, $cmp = null, $language = 'french')
        {
            if (empty($string) || empty($cmp)) {
                return false;
            }
            if (strlen($string) > 255 || strlen($cmp) > 255) {
                return false;
            }

            $processedStr   = $this->phoneme($string, $language);
            $processedCmp   = $this->phoneme($cmp, $language);
            $score          = levenshtein($processedStr, $processedCmp);
            $smaller        = (strlen($processedStr) < strlen($processedCmp)) ? $processedStr : $processedCmp;
            $biggest        = (strlen($processedStr) < strlen($processedCmp)) ? $processedCmp : $processedStr;

            $phonexStr      = $this->getPhonex($string);
            $phonexCmp      = $this->getPhonex($cmp);

            $scorePhonex    = ($phonexStr + $phonexCmp) / 2;

            $contain        = strstr($biggest, $smaller);

            $avgLength      = (strlen($processedStr) + strlen($processedCmp)) / 2;
            $finalScore     = round((1.0 / $avgLength) * $score, 6);
            $finalScore     = round(((1 - $finalScore) * 100), 2);

            $finalScore     = (0 > $finalScore && false !== $contain) ? (100 + $finalScore) : $finalScore;
            $finalScore     = (100 < $finalScore) ? 100 : $finalScore;
            $finalScore     = (0 > $finalScore) ? 0 : $finalScore;

            if($finalScore / 100 >= $this->_tolerance) {
                $grade = 1;
            } else {
                $grade = 0;
            }

            $proxMatch  = self::checkProx(self::_iconv($string), self::_iconv($comp));
            $pctg       = ($finalScore > $proxMatch) ? $finalScore : $proxMatch;
            if (strstr($string, ' ') && strstr($comp, ' ')) {
                $matchWords = self::matchWords(self::_iconv($string), self::_iconv($comp));
                $pctg       = ($matchWords > $pctg) ? $matchWords : $pctg;
            }
            $finalScore  = ($pctg > $bestScore) ? $pctg : $bestScore;


            $data = array(
                'cost'      =>  $score,
                'score'     =>  $finalScore,
                'similar'   =>  $grade
            );
            return $data;
        }


        /**
        * Transform a given string into its phoneme equivalent.
        *
        * @param string $string The string to be transformed in phonemes.
        * @param string $language The language to make phonemes
        * @return string Phoneme string.
        **/
        public function phoneme($string = '', $language = 'french')
        {
            $parts = explode(' ', $string);
            $phonemes = array();
            foreach($parts as $p) {
                $p = $this->partCases(Inflector::lower($p));
                $phon = $this->$language($p);
                if ($phon != ' ' && strlen($phon)) {
                    array_push($phonemes, $phon);
                }
            }
            if($this->_sort) {
                sort($phonemes);
            }
            $string = implode(' ', $phonemes);
            return $string;
        }

        private function partCases($word)
        {
            $particular = array(
                'un',
                'une',
                'de',
                'la',
                'le',
                'les',
                'a',
                'aux',
                'and',
                'the',
                'et',
                'or',
                'ou',
                'où',
                'si',
                'ni',
            );
            if (in_array($word, $particular)) {
                return '';
            }

            $others = array(
                '13h',
                '20h',
                '12h',
                '24h',
                '6h',
                '7h',
                1,
                2,
                3,
                4,
                5,
                6,
                7,
                8,
                9,
                10,
                20,
                30,
                40,
                50,
                60,
                70,
                80,
                90,
                100,
                1000,
                1000000,
                1000000000,
            );

            $otherReplace = array(
                metaphone('thirteen'),
                metaphone('twenty'),
                metaphone('midi'),
                metaphone('midnight'),
                metaphone('six'),
                metaphone('seven'),
                '',
                metaphone('two'),
                metaphone('three'),
                metaphone('four'),
                metaphone('five'),
                metaphone('six'),
                metaphone('seven'),
                metaphone('eight'),
                metaphone('nine'),
                metaphone('ten'),
                metaphone('twenty'),
                metaphone('thirty'),
                metaphone('forty'),
                metaphone('fifty'),
                metaphone('sixty'),
                metaphone('seventy'),
                metaphone('eighty'),
                metaphone('ninety'),
                metaphone('hundred'),
                metaphone('thousand'),
                metaphone('million'),
                metaphone('billion')
            );
            $word = repl($others, $otherReplace, $word);
            return $word;
        }

        /**
        * private function aReplace ()
        * method used to replace letters, given an array
        * @Param array aTab : the replacement array to be used
        * @Param bool bPreg : is the array an array of regular expressions patterns : true => yes`| false => no
        */
        private function aReplace($string, array $aTab, $bPreg = false)
        {
            if (false === $bPreg) {
                $string = repl(array_keys($aTab), array_values($aTab), $string);
            } else {
                $string = preg_replace(array_keys($aTab), array_values($aTab), $string);
            }
        }

        /**
        * private function trimLast ()
        * method to trim the bad endings
        */
        private function trimLast($string)
        {
            $length = strlen($string) - 1;
            if (in_array($string{$length}, array('t', 'x'))) {
                $string = substr($string, 0, $length);
            }
        }

        /**
        * private static function mapNum ()
        * callback method to create the phonex numeric code, base 22
        * @Param int val : current value
        * @Param int clef : current key
        * @Returns int num : the calculated base 22 value
        */
        private static function mapNum($val, $key)
        {
            $except = array (
                '1',
                 '2',
                 '3',
                 '4',
                 '5',
                 'e',
                 'f',
                 'g',
                 'h',
                 'i',
                 'k',
                 'l',
                 'n',
                 'o',
                 'r',
                 's',
                 't',
                 'u',
                 'w',
                 'x',
                 'y',
                 'z'
            );
            $num = array_search($val, $except);
            $num *= pow (22, - ($key + 1));
            return $num;
        }

        /**
        * private function getNum ()
        * method to get a numeric array from the main string
        * we call the callback function mapNum and we sum all the values of the obtained array to get the final phonex code
        */
        private function getPhonex($string)
        {
            $array = str_split($string);
            $aNum = array_map(array ('self', 'mapNum'), array_values($array), array_keys($array));
            $score = (float) array_sum($aNum) * 1000;
            return round($score, 3);
        }

        protected function french($string)
        {
            $accents = array(
                'É' => 'E',
                'È' => 'E',
                'Ë' => 'E',
                'Ê' => 'E',
                'Á' => 'A',
                'À' => 'A',
                'Ä' => 'A',
                'Â' => 'A',
                'Å' => 'A',
                'Ã' => 'A',
                'Æ' => 'E',
                'Ï' => 'I',
                'Î' => 'I',
                'Ì' => 'I',
                'Í' => 'I',
                'Ô' => 'O',
                'Ö' => 'O',
                'Ò' => 'O',
                'Ó' => 'O',
                'Õ' => 'O',
                'Ø' => 'O',
                'Œ' => 'OEU',
                'Ú' => 'U',
                'Ù' => 'U',
                'Û' => 'U',
                'Ü' => 'U',
                'Ñ' => 'N',
                'Ç' => 'S',
                '¿' => 'E'
            );

            $low2up = array(
                'é' => 'É',
                'è' => 'È',
                'ë' => 'Ë',
                'ê' => 'Ê',
                'á' => 'Á',
                'â' => 'Â',
                'à' => 'À',
                'Ä' => 'A',
                'Â' => 'A',
                'å' => 'Å',
                'ã' => 'Ã',
                'æ' => 'Æ',
                'ï' => 'Ï',
                'î' => 'Î',
                'ì' => 'Ì',
                'í' => 'Í',
                'ô' => 'Ô',
                'ö' => 'Ö',
                'ò' => 'Ò',
                'ó' => 'Ó',
                'õ' => 'Õ',
                'ø' => 'Ø',
                'œ' => 'Œ',
                'ú' => 'Ú',
                'ù' => 'Ù',
                'û' => 'Û',
                'ü' => 'Ü',
                'ç' => 'Ç',
                'ñ' => 'Ñ',
                'ß' => 'S'
            );

            if (false === Utils::isUtf8($string)) {
                $string = utf8_encode($string);
            }

            $string = strtr($string, $low2up);                  // minuscules accentuées ou composées en majuscules simples
            $string = strtr($string, $accents);                // majuscules accentuées ou composées en majuscules simples
            $string = Inflector::upper($string);                      // majuscules
            $string = preg_replace('`[^A-Z]`', '', $string);  // A à Z

            $sBack = $string;

            $string = preg_replace('`O[O]+`', 'OU', $string);
            $string = preg_replace('`SAOU`', 'SOU', $string);
            $string = preg_replace('`OES`', 'OS', $string);
            $string = preg_replace('`CCH`', 'K', $string);
            $string = preg_replace('`CC([IYE])`', 'KS$1', $string);
            $string = preg_replace('`(.)\1`', '$1', $string);

            // quelques cas particuliers
            if ($string == 'CD')  {
                return($string);
            }
            if ($string == 'BD')  {
                return($string);
            }
            if ($string == 'BV')  {
                return($string);
            }
            if ($string == 'TABAC')  {
                return('TABA');
            }
            if ($string == 'FEU')  {
                return('FE');
            }
            if ($string == 'FE')  {
                return($string);
            }
            if ($string == 'FER')  {
                return($string);
            }
            if ($string == 'FIEF')  {
                return($string);
            }
            if ($string == 'FJORD')  {
                return($string);
            }
            if ($string == 'GOAL')  {
                return('GOL');
            }
            if ($string == 'FLEAU')  {
                return('FLEO');
            }
            if ($string == 'HIER')  {
                return('IER');
            }
            if ($string == 'HEU')  {
                return('E');
            }
            if ($string == 'HE')  {
                return('E');
            }
            if ($string == 'OS')  {
                return($string);
            }
            if ($string == 'RIZ')  {
                return('RI');
            }
            if ($string == 'RAZ')  {
                return('RA');
            }

            // pré-traitements
            $string = preg_replace('`OIN[GT]$`', 'OIN', $string);                                   // terminaisons OING -> OIN
            $string = preg_replace('`E[RS]$`', 'E', $string);                                       // supression des terminaisons infinitifs et participes pluriels
            $string = preg_replace('`(C|CH)OEU`', 'KE', $string);                                   // pré traitement OEU -> EU
            $string = preg_replace('`MOEU`', 'ME', $string);                                        // pré traitement OEU -> EU
            $string = preg_replace('`OE([UI]+)([BCDFGHJKLMNPQRSTVWXZ])`', 'E$1$2', $string);        // pré traitement OEU OEI -> E
            $string = preg_replace('`^GEN[TS]$`', 'JAN', $string);                                  // pré traitement GEN -> JAN
            $string = preg_replace('`CUEI`', 'KEI', $string);                                       // pré traitement accueil
            $string = preg_replace('`([^AEIOUYC])AE([BCDFGHJKLMNPQRSTVWXZ])`', '$1E$2', $string);   // pré traitement AE -> E
            $string = preg_replace('`AE([QS])`', 'E$1', $string);                                   // pré traitement AE -> E
            $string = preg_replace('`AIE([BCDFGJKLMNPQRSTVWXZ])`', 'AI$1', $string);                // pré-traitement AIE(consonne) -> AI
            $string = preg_replace('`ANIEM`', 'ANIM', $string);                                     // pré traitement NIEM -> NIM
            $string = preg_replace('`(DRA|TRO|IRO)P$`', '$1', $string);                             // P terminal muet
            $string = preg_replace('`(LOM)B$`', '$1', $string);                                     // B terminal muet
            $string = preg_replace('`(RON|POR)C$`', '$1', $string);                                 // C terminal muet
            $string = preg_replace('`PECT$`', 'PET', $string);                                      // C terminal muet
            $string = preg_replace('`ECUL$`', 'CU', $string);                                       // L terminal muet
            $string = preg_replace('`(CHA|CA|E)M(P|PS)$`', '$1N', $string);                         // P ou PS terminal muet
            $string = preg_replace('`(TAN|RAN)G$`', '$1', $string);                                 // G terminal muet
            $string = repl('JT', 'JOURNAL', $string);                                               // JT devient journal

            $string = preg_replace('`([^VO])ILAG`', '$1IAJ', $string);
            $string = preg_replace('`([^TRH])UIL(AR|E)(.+)`', '$1UI$2$3', $string);
            $string = preg_replace('`([G])UIL([AEO])`', '$1UI$2', $string);
            $string = preg_replace('`([NSPM])AIL([AEO])`', '$1AI$2', $string);
            $convMIn  = array(
                "DILAI",
                "DILON",
                "DILER",
                "DILEM",
                "RILON",
                "TAILE",
                "GAILET",
                "AILAI",
                "AILAR",
                "OUILA",
                "EILAI",
                "EILAR",
                "EILER",
                "EILEM",
                "REILET",
                "EILET",
                "AILOL"
            );
            $convMOut = array(
                "DIAI",
                "DION",
                "DIER",
                "DIEM",
                "RION",
                "TAIE",
                "GAIET",
                "AIAI",
                "AIAR",
                "OUIA",
                "AIAI",
                "AIAR",
                "AIER",
                "AIEM",
                "RAIET",
                "EIET",
                "AIOL"
            );
            $string = repl($convMIn, $convMOut, $string);
            $string = preg_replace('`([^AEIOUY])(SC|S)IEM([EA])`', '$1$2IAM$3', $string);   // IEM -> IAM
            $string = preg_replace('`^(SC|S)IEM([EA])`', '$1IAM$2', $string);               // IEM -> IAM

            $convMIn  = array(
                'OMB',
                'AMB',
                'OMP',
                'AMP',
                'IMB',
                'EMP',
                'GEMB',
                'EMB',
                'UMBL',
                'CIEN'
            );
            $convMOut = array(
                'ONB',
                'ANB',
                'ONP',
                'ANP',
                'INB',
                'ANP',
                'JANB',
                'ANB',
                'INBL',
                'SIAN'
            );
            $string = repl($convMIn, $convMOut, $string);

            $string = preg_replace('`^ECHO$`', 'EKO', $string);     // cas particulier écho
            $string = preg_replace('`^ECEUR`', 'EKEUR', $string);   // cas particulier écoeuré
            $string = preg_replace('`^CH(OG+|OL+|OR+|EU+|ARIS|M+|IRO|ONDR)`', 'K$1', $string);              //En début de mot
            $string = preg_replace('`(YN|RI)CH(OG+|OL+|OC+|OP+|OM+|ARIS|M+|IRO|ONDR)`', '$1K$2', $string);  //Ou devant une consonne
            $string = preg_replace('`CHS`', 'CH', $string);
            $string = preg_replace('`CH(AIQ)`', 'K$1', $string);
            $string = preg_replace('`^ECHO([^UIPY])`', 'EKO$1', $string);
            $string = preg_replace('`ISCH(I|E)`', 'ISK$1', $string);
            $string = preg_replace('`^ICHT`', 'IKT', $string);
            $string = preg_replace('`ORCHID`', 'ORKID', $string);
            $string = preg_replace('`ONCHIO`', 'ONKIO', $string);
            $string = preg_replace('`ACHIA`', 'AKIA', $string);         // retouche ACHIA -> AKIA
            $string = preg_replace('`([^C])ANICH`', '$1ANIK', $string); // ANICH -> ANIK    1/2
            $string = preg_replace('`OMANIK`', 'OMANICH', $string);     // cas particulier  2/2
            $string = preg_replace('`ACHY([^D])`', 'AKI$1', $string);
            $string = preg_replace('`([AEIOU])C([BDFGJKLMNPQRTVWXZ])`', '$1K$2', $string); // voyelle, C, consonne sauf H
            $convPrIn  = array(
                'EUCHA',
                'YCHIA',
                'YCHA',
                'YCHO',
                'YCHED',
                'ACHEO',
                'RCHEO',
                'RCHES',
                'ECHN',
                'OCHTO',
                'CHORA',
                'CHONDR',
                'CHORE',
                'MACHM',
                'BRONCHO',
                'LICHOS',
                'LICHOC'
            );
            $convPrOut = array(
                'EKA',
                'IKIA',
                'IKA',
                'IKO',
                'IKED',
                'AKEO',
                'RKEO',
                'RKES',
                'EKN',
                'OKTO',
                'KORA',
                'KONDR',
                'KORE',
                'MAKM',
                'BRONKO',
                'LIKOS',
                'LIKOC'
            );
            $string = repl($convPrIn, $convPrOut, $string);

            $convPrIn  = array(
                'WA',
                'WO',
                'WI',
                'WHI',
                'WHY',
                'WHA',
                'WHO'
            );
            $convPrOut = array(
                'OI',
                'O',
                'OUI',
                'OUI',
                'OUI',
                'OUA',
                'OU'
            );
            $string = repl($convPrIn, $convPrOut, $string);

            $convPrIn  = array(
                'GNES',
                'GNET',
                'GNER',
                'GNE',
                'GI',
                'GNI',
                'GNA',
                'GNOU',
                'GNUR',
                'GY',
                'OUGAIN',
                'AGEOL',
                'AGEOT',
                'GEOLO',
                'GEOM',
                'GEOP',
                'GEOG',
                'GEOS',
                'GEORG',
                'GEOR',
                'NGEOT',
                'UGEOT',
                'GEOT',
                'GEOD',
                'GEOC',
                'GEO',
                'GEA',
                'GE',
                'QU',
                'Q',
                'CY',
                'CI',
                'CN',
                'ICM',
                'CEAT',
                'CE',
                'CR',
                'CO',
                'CUEI',
                'CU',
                'VENCA',
                'CA',
                'CS',
                'CLEN',
                'CL',
                'CZ',
                'CTIQ',
                'CTIF',
                'CTIC',
                'CTIS',
                'CTIL',
                'CTIO',
                'CTI',
                'CTU',
                'CTE',
                'CTO',
                'CTR',
                'CT',
                'PH',
                'TH',
                'OW',
                'LH',
                'RDL',
                'CHLO',
                'CHR',
                'PTIA'
            );
            $convPrOut = array(
                'NIES',
                'NIET',
                'NIER',
                'NE',
                'JI',
                'NI',
                'NIA',
                'NIOU',
                'NIUR',
                'JI',
                'OUGIN',
                'AJOL',
                'AJOT',
                'JEOLO',
                'JEOM',
                'JEOP',
                'JEOG',
                'JEOS',
                'JORJ',
                'JEOR',
                'NJOT',
                'UJOT',
                'JEOT',
                'JEOD',
                'JEOC',
                'JO',
                'JA' ,
                'JE',
                'K',
                'K',
                'SI',
                'SI',
                'KN',
                'IKM',
                'SAT',
                'SE',
                'KR',
                'KO',
                'KEI',
                'KU',
                'VANSA',
                'KA',
                'KS',
                'KLAN',
                'KL',
                'KZ',
                'KTIK',
                'KTIF',
                'KTIS',
                'KTIS',
                'KTIL',
                'KSIO',
                'KTI',
                'KTU',
                'KTE',
                'KTO',
                'KTR',
                'KT',
                'F',
                'T',
                'OU',
                'L',
                'RL',
                'KLO',
                'KR',
                'PSIA'
            );
            $string = repl($convPrIn, $convPrOut, $string);

            $string = preg_replace('`GU([^RLMBSTPZN])`', 'G$1', $string);
            $string = preg_replace('`GNO([MLTNRKG])`', 'NIO$1', $string);
            $string = preg_replace('`GNO([MLTNRKG])`', 'NIO$1', $string);

            $convPrIn  = array(
                'BUTIE',
                'BUTIA',
                'BATIA',
                'ANTIEL',
                'RETION',
                'ENTIEL',
                'ENTIAL',
                'ENTIO',
                'ENTIAI',
                'UJETION',
                'ATIEM',
                'PETIEN',
                'CETIE',
                'OFETIE',
                'IPETI',
                'LBUTION',
                'BLUTION',
                'LETION',
                'LATION',
                'SATIET'
            );
            $convPrOut = array(
                'BUSIE',
                'BUSIA',
                'BASIA',
                'ANSIEL',
                'RESION',
                'ENSIEL',
                'ENSIAL',
                'ENSIO',
                'ENSIAI',
                'UJESION',
                'ASIAM',
                'PESIEN',
                'CESIE',
                'OFESIE',
                'IPESI',
                'LBUSION',
                'BLUSION',
                'LESION',
                'LASION',
                'SASIET'
            );
            $string = repl($convPrIn, $convPrOut, $string);
            $string = preg_replace('`(.+)ANTI(AL|O)`', '$1ANSI$2', $string);
            $string = preg_replace('`(.+)INUTI([^V])`', '$1INUSI$2', $string);
            $string = preg_replace('`([^O])UTIEN`', '$1USIEN', $string);
            $string = preg_replace('`([^DE])RATI[E]$`', '$1RASI$2', $string);
            $string = preg_replace('`([^SNEU]|KU|KO|RU|LU|BU|TU|AU)T(IEN|ION)`', '$1S$2', $string);

            $string = preg_replace('`([^CS])H`', '$1', $string);    // H muet
            $string = repl("ESH", "ES", $string);            // H muet
            $string = repl("NSH", "NS", $string);            // H muet
            $string = repl("SH", "CH", $string);

            $convNasIn  = array(
                'OMT',
                'IMB',
                'IMP',
                'UMD',
                'TIENT',
                'RIENT',
                'DIENT',
                'IEN',
                'YMU',
                'YMO',
                'YMA',
                'YME',
                'YMI',
                'YMN',
                'YM',
                'AHO',
                'FAIM',
                'DAIM',
                'SAIM',
                'EIN',
                'AINS'
            );
            $convNasOut = array(
                'ONT',
                'INB',
                'INP',
                'OND',
                'TIANT',
                'RIANT',
                'DIANT',
                'IN',
                'IMU',
                'IMO',
                'IMA',
                'IME',
                'IMI',
                'IMN',
                'IN',
                'AO',
                'FIN',
                'DIN',
                'SIN',
                'AIN',
                'INS'
            );
            $string = repl($convNasIn, $convNasOut, $string);

            $string = preg_replace('`AIN$`', 'IN', $string);
            $string = preg_replace('`AIN([BTDK])`', 'IN$1', $string);

            $string = preg_replace('`([^O])UND`', '$1IND', $string);
            $string = preg_replace('`([JTVLFMRPSBD])UN([^IAE])`', '$1IN$2', $string);
            $string = preg_replace('`([JTVLFMRPSBD])UN$`', '$1IN', $string);
            $string = preg_replace('`RFUM$`', 'RFIN', $string);
            $string = preg_replace('`LUMB`', 'LINB', $string);

            $string = preg_replace('`([^BCDFGHJKLMNPQRSTVWXZ])EN`', '$1AN', $string);
            $string = preg_replace('`([VTLJMRPDSBFKNG])EN([BRCTDKZSVN])`', '$1AN$2', $string); // on bisse pour les 2bles nasales
            $string = preg_replace('`([VTLJMRPDSBFKNG])EN([BRCTDKZSVN])`', '$1AN$2', $string);
            $string = preg_replace('`^EN([BCDFGHJKLNPQRSTVXZ]|CH|IV|ORG|OB|UI|UA|UY)`', 'AN$1', $string);
            $string = preg_replace('`(^[JRVTH])EN([DRTFGSVJMP])`', '$1AN$2', $string);
            $string = preg_replace('`SEN([ST])`', 'SAN$1', $string);
            $string = preg_replace('`^DESENIV`', 'DESANIV', $string);
            $string = preg_replace('`([^M])EN(UI)`', '$1AN$2', $string);
            $string = preg_replace('`(.+[JTVLFMRPSBD])EN([JLFDSTG])`', '$1AN$2', $string);
            $string = preg_replace('`([VSBSTNRLPM])E[IY]([ACDFRJLGZ])`', '$1AI$2', $string);

            $convNasIn  = array(
                'EAU',
                'EU',
                'Y',
                'EOI',
                'JEA',
                'OIEM',
                'OUANJ',
                'OUA',
                'OUENJ'
            );
            $convNasOut = array(
                'O',
                'E',
                'I',
                'OI',
                'JA',
                'OIM' ,
                'OUENJ',
                'OI',
                'OUANJ'
            );
            $string = repl($convNasIn, $convNasOut, $string);
            $string = preg_replace('`AU([^E])`', 'O$1', $string);

            $string = preg_replace('`^BENJ`', 'BINJ', $string);             // BENJ -> BINJ
            $string = preg_replace('`RTIEL`', 'RSIEL', $string);            // RTIEL -> RSIEL
            $string = preg_replace('`PINK`', 'PONK', $string);              // PINK -> PONK
            $string = preg_replace('`KIND`', 'KOND', $string);              // KIND -> KOND
            $string = preg_replace('`KUM(N|P)`', 'KON$1', $string);         // KUMN KUMP
            $string = preg_replace('`LKOU`', 'LKO', $string);               // LKOU -> LKO
            $string = preg_replace('`EDBE`', 'EBE', $string);               // EDBE pied-buf
            $string = preg_replace('`ARCM`', 'ARKM', $string);              // SCH -> CH
            $string = preg_replace('`SCH`', 'CH', $string);                 // SCH -> CH
            $string = preg_replace('`^OINI`', 'ONI', $string);              // OINI -> ONI
            $string = preg_replace('`([^NDCGRHKO])APT`', '$1AT', $string);  // APT -> AT
            $string = preg_replace('`([L]|KON)PT`', '$1T', $string);        // LPT -> LT
            $string = preg_replace('`OTB`', 'OB', $string);                 // OTB -> OB (hautbois)
            $string = preg_replace('`IXA`', 'ISA', $string);                // IXA -> ISA
            $string = preg_replace('`TG`', 'G', $string);                   // TG -> G
            $string = preg_replace('`^TZ`', 'TS', $string);                 // TZ -> TS
            $string = preg_replace('`PTIE`', 'TIE', $string);               // PTIE -> TIE
            $string = preg_replace('`GT`', 'T', $string);                   // GT -> T
            $string = repl("ANKIEM", "ANKILEM", $string);
            $string = preg_replace("`(LO|RE)KEMAN`", "$1KAMAN", $string);   // KEMAN -> KAMAN
            $string = preg_replace('`NT(B|M)`', 'N$1', $string);            // TB -> B  TM -> M
            $string = preg_replace('`GSU`', 'SU', $string);                 // GS -> SU
            $string = preg_replace('`ESD`', 'ED', $string);                 // ESD -> ED
            $string = preg_replace('`LESKEL`','LEKEL', $string);            // LESQUEL -> LEKEL
            $string = preg_replace('`CK`', 'K', $string);                   // CK -> K

            // fins de mots
            $string = preg_replace('`USIL$`', 'USI', $string);              // USIL -> USI
            $string = preg_replace('`X$|[TD]S$|[DS]$`', '', $string);       // TS DS LS X T D S...  v2.0
            $string = preg_replace('`([^KL]+)T$`', '$1', $string);
            $string = preg_replace('`^[H]`', '', $string);                  // H muet en début de mot

            $sBack2 = $string;

            $string = preg_replace('`TIL$`', 'TI', $string);                // TIL -> TI
            $string = preg_replace('`LC$`', 'LK', $string);                 // LC -> LK
            $string = preg_replace('`L[E]?[S]?$`', 'L', $string);           // LE LES -> L
            $string = preg_replace('`(.+)N[E]?[S]?$`', '$1N', $string);     // NE NES -> N
            $string = preg_replace('`EZ$`', 'E', $string);                  // EZ -> E
            $string = preg_replace('`OIG$`', 'OI', $string);                // OIG -> OI
            $string = preg_replace('`OUP$`', 'OU', $string);                // OUP -> OU
            $string = preg_replace('`([^R])OM$`', '$1ON', $string);         // OM -> ON sauf ROM
            $string = preg_replace('`LOP$`', 'LO', $string);                // LOP -> LO
            $string = preg_replace('`NTANP$`', 'NTAN', $string);            // NTANP -> NTAN
            $string = preg_replace('`TUN$`', 'TIN', $string);               // TUN -> TIN
            $string = preg_replace('`AU$`', 'O', $string);                  // AU -> O
            $string = preg_replace('`EI$`', 'AI', $string);                 // EI -> AI
            $string = preg_replace('`R[DG]$`', 'R', $string);               // RD RG -> R
            $string = preg_replace('`ANC$`', 'AN', $string);                // ANC -> AN
            $string = preg_replace('`KROC$`', 'KRO', $string);              // C muet de CROC, ESCROC
            $string = preg_replace('`HOUC$`', 'HOU', $string);              // C muet de CAOUTCHOUC
            $string = preg_replace('`OMAC$`', 'OMA', $string);              // C muet de ESTOMAC (mais pas HAMAC)
            $string = preg_replace('`([J])O([NU])[CG]$`', '$1O$2', $string);// C et G muet de OUC ONC OUG
            $string = preg_replace('`([^GTR])([AO])NG$`', '$1$2N', $string);// G muet ANG ONG sauf GANG GONG TANG TONG
            $string = preg_replace('`UC$`', 'UK', $string);                 // UC -> UK
            $string = preg_replace('`AING$`', 'IN', $string);               // AING -> IN
            $string = preg_replace('`([EISOARN])C$`', '$1K', $string);      // C -> K
            $string = preg_replace('`([ABD-MO-Z]+)[EH]+$`', '$1', $string); // E ou H
            $string = preg_replace('`EN$`', 'AN', $string);                 // EN -> AN
            $string = preg_replace('`(NJ)EN$`', '$1AN', $string);           // EN -> AN
            $string = preg_replace('`^PAIEM`', 'PAIM', $string);            // PAIE -> PAI
            $string = preg_replace('`([^NTB])EF$`', '\1', $string);         // F muet

            $string = preg_replace('`(.)\1`', '$1', $string);

            /* part cases */
            $convPartIn  = array('FUEL');
            $convPartOut = array('FIOUL');
            $string = repl($convPartIn, $convPartOut, $string);

            if ($string == 'O') {
                return($string);
            }

            if ($string == 'C') {
                return('SE');
            }

            if (strlen($string) < 2) {
                // acronymes
                if (preg_match("`[BCDFGHJKLMNPQRSTVWXYZ][BCDFGHJKLMNPQRSTVWXYZ][BCDFGHJKLMNPQRSTVWXYZ][BCDFGHJKLMNPQRSTVWXYZ]*`", $sBack)) {
                    return($sBack);
                }

                if (preg_match("`[RFMLVSPJDF][AEIOU]`", $sBack)) {
                    if (strlen($sBack) == 3) {
                        return(substr($sBack, 0, 2)); // mots de 3 lettres
                    }

                    if (strlen($sBack) == 4)
                        return(substr($sBack, 0, 3)); // mots de 4 lettres
                }

                if (strlen($sBack2) > 1) {
                    return $sBack2;
                }
            }

            if (strlen($string) > 1) {
                return substr($string, 0, 32);
            } else {
                return '';
            }
        }

        protected function english($string)
        {
            return metaphone($string);
        }

        private static function matchWords($w1, $w2)
        {
            $w1 = self::_clean($w1);
            $w2 = self::_clean($w2);
            $words1 = explode(' ', $w1);
            $words2 = explode(' ', $w2);

            $firstW1 = current($words1);
            $firstW2 = current($words2);

            $lastW1 = end($words1);
            $lastW2 = end($words2);

            $commonWords = array_intersect($words1, $words2);
            $pctg = (count($commonWords) / count($words2)) * 100;
            return round($pctg, 2);
        }

        private static function _clean($str)
        {
            $str = Inflector::lower($str);
            $str = repl(' & ', ' et ', $str);
            $str = repl('...', '', $str);
            $str = repl('.', '', $str);
            $str = repl('?', 'e', $str);
            $str = repl(',', '', $str);
            $str = repl(';', '', $str);
            $str = repl('!', '', $str);
            $str = repl(':', '', $str);
            $str = repl('/', '', $str);
            $str = repl('+', '', $str);
            $str = repl('-', '', $str);
            $str = repl('*', '', $str);
            $str = repl(' and ', ' et ', $str);
            //*GP* $str = repl('jt', 'journal', $str);
            return $str;
        }

        private static function checkProx($w1, $w2)
        {
            $w1 = self::_clean($w1);
            $w2 = self::_clean($w2);
            $w1 = repl(' ', '', $w1);
            $w2 = repl(' ', '', $w2);

            $distance = self::distanceWords($w1, $w2);
            $m = (strlen($w1) > strlen($w2)) ? strlen($w1) : strlen($w2);
            $pctgDistance = (1 - $distance / $m) * 100;

            $similar1 = similar_text(metaphone($w1), metaphone($w2), $matchPhonetic);
            $similar2 = similar_text($w1, $w2, $matchNormal);

            $proxMatch = ($pctgDistance > $matchPhonetic) ? $pctgDistance : $matchPhonetic;

            $globalMatch = ($proxMatch + $matchNormal) / 2;
            return round($globalMatch, 2);
        }

        protected static function distanceWords($w1, $w2)
        {
            $sLeft          = (strlen($w1) > strlen($w2)) ? $w1 : $w2;
            $sRight         = (strlen($w1) > strlen($w2)) ? $w2 : $w1;
            $nLeftLength    = strlen($sLeft);
            $nRightLength   = strlen($sRight);
            if ($nLeftLength == 0) {
                return $nRightLength;
            } else if ($nRightLength == 0) {
                return $nLeftLength;
            } else if ($sLeft === $sRight) {
                return 0;
            } else if (($nLeftLength < $nRightLength) && (strpos($sRight, $sLeft) !== false)) {
                return $nRightLength - $nLeftLength;
            } else if (($nRightLength < $nLeftLength) && (strpos($sLeft, $sRight) !== false)) {
                return $nLeftLength - $nRightLength;
            } else {
                $nsDistance = range(1, $nRightLength + 1);
                for ($nLeftPos = 1 ; $nLeftPos <= $nLeftLength ; $nLeftPos++) {
                    $cLeft = $sLeft[$nLeftPos - 1];
                    $nDiagonal = $nLeftPos - 1;
                    $nsDistance[0] = $nLeftPos;
                    for ($nRightPos = 1 ; $nRightPos <= $nRightLength ; $nRightPos++) {
                        $cRight = $sRight[$nRightPos - 1];
                        $nCost = ($cRight == $cLeft) ? 0 : 1;
                        $nNewDiagonal = $nsDistance[$nRightPos];
                        $nsDistance[$nRightPos] = min($nsDistance[$nRightPos] + 1, $nsDistance[$nRightPos - 1] + 1, $nDiagonal + $nCost);
                        $nDiagonal = $nNewDiagonal;
                    }
                }
                return $nsDistance[$nRightLength];
            }
        }

        public static function _iconv($str)
        {
            if (!u::isUtf8($str)) {
                $str = utf8_encode($str);
            }
            setlocale(LC_CTYPE, 'fr_FR');
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
        }
    }

?>
