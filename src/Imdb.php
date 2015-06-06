<?php
    namespace Thin;

    use Thin\Imdb\Helper as IMDBHelper;

    class Imdb
    {
        /**
         * Set this to true if you run into problems.
         */
        const IMDB_DEBUG = false;

        /**
         * Set the prefered language for the User Agent.
         */
        const IMDB_LANG = 'en, en-US;q=0.8';

        /**
         * Define the timeout for cURL requests.
         */
        const IMDB_TIMEOUT = 15;

        /**
         * @var int Maximum cache time.
         */
        private $iCache = 1440;

        /**
         * @var null|string The root of the script.
         */
        private $sRoot = null;

        /**
         * @var null|string Holds the source.
         */
        private $sSource = null;

        /**
         * @var null|int The ID of the movie.
         */
        private $iId = null;

        /**
         * @var string What to search for?
         */
        private $sSearchFor = 'all';

        /**
         * @var bool Is the content ready?
         */
        public $isReady = false;

        /**
         * @var string The string returned, if nothing is found.
         */
        public $sNotFound = 'n/A';

        /**
         * @var string Char that separates multiple entries.
         */
        public $sSeparator = ' / ';

        /**
         * @var null|string The URL to the movie.
         */
        public $sUrl = null;

        /**
         * @var bool Return reponses eclosed in array
         */
        public $bArrayOutput = false;

        /**
         * These are the regular expressions used to extract the data.
         * If you don’t know what you’re doing, you shouldn’t touch them.
         */
        const IMDB_AKA           = '~<h5>Also Known As:<\/h5>(?:\s*)<div class="info-content">(?:\s*)"(.*)"~Ui';
        const IMDB_ASPECT_RATIO  = '~<h5>Aspect Ratio:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_AWARDS        = '~<h5>Awards:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_CAST          = '~<td class="nm"><a href="\/name\/(.*)\/"(?:.*)>(.*)<\/a><\/td>~Ui';
        const IMDB_CERTIFICATION = '~<h5>Certification:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_CHAR          = '~<td class="char">(.*)<\/td>~Ui';
        const IMDB_COLOR         = '~<h5>Color:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_COMPANY       = '~<h5>Company:<\/h5>(?:\s*)<div class="info-content"><a href="\/company\/(.*)\/">(.*)</a>(?:.*)<\/div>~Ui';
        const IMDB_COUNTRY       = '~<a href="/country/(\w+)">(.*)</a>~Ui';
        const IMDB_CREATOR       = '~<h5>(?:Creator|Creators):<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_DIRECTOR      = '~<h5>(?:Director|Directors):<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_GENRE         = '~<a href="\/Sections\/Genres\/(\w+)\/">(.*)<\/a>~Ui';
        const IMDB_ID            = '~((?:tt\d{6,})|(?:itle\?\d{6,}))~';
        const IMDB_LANGUAGE      = '~<a href="\/language\/(\w+)">(.*)<\/a>~Ui';
        const IMDB_LOCATION      = '~href="\/search\/title\?locations=(.*)">(.*)<\/a>~Ui';
        const IMDB_MOVIEMETER    = '~<h5>MOVIEmeter:(?:.*)<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_MPAA          = '~<h5><a href="\/mpaa">MPAA<\/a>:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_NAME          = '~href="\/name\/(.*)\/"(?:.*)>(.*)<\/a>~Ui';
        const IMDB_NOT_FOUND     = '~<h1 class="findHeader">No results found for ~Ui';
        const IMDB_PLOT          = '~<h5>Plot:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_PLOT_KEYWORDS = '~<h5>Plot Keywords:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_POSTER        = '~<link rel="image_src" href="(.*)">~Ui';
        const IMDB_RATING        = '~<div class="starbar-meta">(?:\s*)<b>(.*)\/10<\/b>~Ui';
        const IMDB_RELEASE_DATE  = '~<h5>Release Date:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_RUNTIME       = '~<h5>Runtime:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_SEARCH        = '~<td class="result_text"> <a href="\/title\/(tt\d{6,})\/(?:.*)"(?:\s*)>(?:.*)<\/a>~Ui';
        const IMDB_SEASONS       = '~(?:episodes\?season=(\d+))~Ui';
        const IMDB_SOUND_MIX     = '~<h5>Sound Mix:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_TAGLINE       = '~<h5>Tagline:<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_TITLE         = '~property="og:title" content="(.*)"~Ui';
        const IMDB_TITLE_ORIG    = '~<span class="title-extra">(.*) <i>\(original title\)<\/i></span>~Ui';
        const IMDB_TRAILER       = '~data-video="(.*)"~Ui';
        const IMDB_URL           = '~http://(?:.*\.|.*)imdb.com/(?:t|T)itle(?:\?|/)(..\d+)~i';
        const IMDB_USER_REVIEW   = '~<h5>User Reviews:<\/h5>(?:\s*)<div class="info-content">(.*)<a~Ui';
        const IMDB_VOTES         = '~<a href="ratings" class="tn15more">(.*) votes<\/a>~Ui';
        const IMDB_WRITER        = '~<h5>(?:Writer|Writers):<\/h5>(?:\s*)<div class="info-content">(.*)<\/div>~Ui';
        const IMDB_YEAR          = '~<a href="\/year\/(?:\d{4})\/">(.*)<\/a>~Ui';

        /**
         * @param string $sSearch IMDb URL or movie title to search for.
         * @param null   $iCache  Custom cache time in minutes.
         *
         * @throws \Exception
         */
        public function __construct($sSearch, $iCache = null, $sSearchFor = 'all')
        {
            $this->sRoot = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__FILE__);

            if (!is_writable($this->sRoot . '/posters') && !mkdir($this->sRoot . '/posters')) {
                throw new Exception('The directory “' . $this->sRoot . '/posters” isn’t writable.');
            }

            if (!is_writable($this->sRoot . '/cache') && !mkdir($this->sRoot . '/cache')) {
                throw new Exception('The directory “' . $this->sRoot . '/cache” isn’t writable.');
            }

            if (!function_exists('curl_init')) {
                throw new Exception('You need to enable the PHP cURL extension.');
            }

            if (in_array($sSearchFor, array(
                'movie',
                'tv',
                'episode',
                'game',
                'all'
            ))) {
                $this->sSearchFor = $sSearchFor;
            }

            if (true === self::IMDB_DEBUG) {
                ini_set('display_errors', 1);
                ini_set('display_startup_errors', 1);
                error_reporting(-1);
                echo '<pre><b>Running:</b> fetchUrl("' . $sSearch . '")</pre>';
            }

            if (null !== $iCache && (int)$iCache > 0) {
                $this->iCache = (int)$iCache;
            }

            $this->fetchUrl($sSearch);
        }

        /**
         * @param string $sSearch IMDb URL or movie title to search for.
         *
         * @return bool True on success, false on failure.
         */
        private function fetchUrl($sSearch)
        {
            $sSearch = trim($sSearch);

            // Try to find a valid URL.
            $sId = IMDBHelper::matchRegex($sSearch, self::IMDB_ID, 1);

            if (false !== $sId) {
                $this->iId  = preg_replace('~[\D]~', '', $sId);
                $this->sUrl = 'http://www.imdb.com/title/tt' . $this->iId . '/combined';
                $bSearch    = false;
            } else {
                switch (strtolower($this->sSearchFor)) {
                    case 'movie':
                        $sParameters = '&s=tt&ttype=ft';
                        break;
                    case 'tv':
                        $sParameters = '&s=tt&ttype=tv';
                        break;
                    case 'episode':
                        $sParameters = '&s=tt&ttype=ep';
                        break;
                    case 'game':
                        $sParameters = '&s=tt&ttype=vg';
                        break;
                    default:
                        $sParameters = '&s=tt';
                }

                $this->sUrl = 'http://www.imdb.com/find?q=' . str_replace(' ', '+', $sSearch) . $sParameters;
                $bSearch    = true;

                // Was this search already performed and cached?
                $sRedirectFile = $this->sRoot . '/cache/' . md5($this->sUrl) . '.redir';

                if (is_readable($sRedirectFile)) {
                    if (self::IMDB_DEBUG) {
                        echo '<pre><b>Using redirect:</b> ' . basename($sRedirectFile) . '</pre>';
                    }

                    $sRedirect  = file_get_contents($sRedirectFile);
                    $this->sUrl = trim($sRedirect);
                    $this->iId  = preg_replace('~[\D]~', '', IMDBHelper::matchRegex($sRedirect, self::IMDB_ID, 1));
                    $bSearch    = false;
                }
            }

            // Does a cache of this movie exist?
            $sCacheFile = $this->sRoot . '/cache/' . md5($this->iId) . '.cache';

            if (is_readable($sCacheFile)) {
                $iDiff = round(abs(time() - filemtime($sCacheFile)) / 60);

                if ($iDiff < $this->iCache) {
                    if (true === self::IMDB_DEBUG) {
                        echo '<pre><b>Using cache:</b> ' . basename($sCacheFile) . '</pre>';
                    }

                    $this->sSource = file_get_contents($sCacheFile);
                    $this->isReady = true;

                    return true;
                }
            }

            // Run cURL on the URL.
            if (true === self::IMDB_DEBUG) {
                echo '<pre><b>Running cURL:</b> ' . $this->sUrl . '</pre>';
            }

            $aCurlInfo = IMDBHelper::runCurl($this->sUrl);
            $sSource   = $aCurlInfo['contents'];

            if (false === $sSource) {
                if (true === self::IMDB_DEBUG) {
                    echo '<pre><b>cURL error:</b> ' . var_dump($aCurlInfo) . '</pre>';
                }

                return false;
            }

            // Was the movie found?
            $sMatch = IMDBHelper::matchRegex($sSource, self::IMDB_SEARCH, 1);

            if (false !== $sMatch) {
                $sUrl = 'http://www.imdb.com/title/' . $sMatch . '/combined';

                if (true === self::IMDB_DEBUG) {
                    echo '<pre><b>New redirect saved:</b> ' . basename($sRedirectFile) . ' => ' . $sUrl . '</pre>';
                }

                file_put_contents($sRedirectFile, $sUrl);
                $this->sSource = null;
                self::fetchUrl($sUrl);

                return true;
            }

            $sMatch = IMDBHelper::matchRegex($sSource, self::IMDB_NOT_FOUND, 0);

            if (false !== $sMatch) {
                if (true === self::IMDB_DEBUG) {
                    echo '<pre><b>Movie not found:</b> ' . $sSearch . '</pre>';
                }

                return false;
            }

            $this->sSource = str_replace(
                array(
                    "\n",
                    "\r\n",
                    "\r"
                ),
                '',
                $sSource
            );

            $this->isReady = true;

            // Save cache.
            if (false === $bSearch) {
                if (true === self::IMDB_DEBUG) {
                    echo '<pre><b>Cache created:</b> ' . basename($sCacheFile) . '</pre>';
                }

                file_put_contents($sCacheFile, $this->sSource);
            }

            return true;
        }

        /**
         * @return string “Also Known As” or $sNotFound.
         */
        public function getAka()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_AKA, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }


        /**
         * Returns all local names
         *
         * @return string The aka name.
         */
        public function getAkas()
        {
            if (true === $this->isReady) {
                $sCacheFile = $this->sRoot . '/cache/' . md5($this->iId) . '_akas.cache';
                $bUseCache = false;

                if (is_readable($sCacheFile)) {
                    $iDiff = round(abs(time() - filemtime($sCacheFile)) / 60);

                    if ($iDiff < $this->iCache || false) {
                        if (true === self::IMDB_DEBUG) {
                            echo '<pre><b>Using cache:</b> ' . basename($sCacheFile) . '</pre>';
                        }

                        $bUseCache = true;
                        $sSource = file_get_contents($sCacheFile);
                    }
                }

                if ($bUseCache) {
                    if (IMDB::IMDB_DEBUG) {
                        echo '<b>- Using cache for Akas from ' . $sCacheFile . '</b><br>';
                    }

                    $aRawReturn = file_get_contents($sCacheFile);
                    $aReturn = unserialize($aRawReturn);

                    return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, $this->sNotFound, $aReturn);
                } else {
                    $fullAkas = sprintf('http://www.imdb.com/title/tt%s/releaseinfo', $this->iId);
                    $aCurlInfo = IMDBHelper::runCurl($fullAkas);
                    $sSource   = $aCurlInfo['contents'];

                    if (false === $sSource) {
                        if (true === self::IMDB_DEBUG) {
                            echo '<pre><b>cURL error:</b> ' . var_dump($aCurlInfo) . '</pre>';
                        }

                        return false;
                    }

                    $aReturned = IMDBHelper::matchRegex($sSource, "~<td>(.*?)<\/td>\s+<td>(.*?)<\/td>~");

                    if ($aReturned) {
                        $aReturn = array();
                        foreach ($aReturned[1] as $i => $strName) {
                              if (strpos($strName,'(')===false) {
                                $aReturn[] = array(
                                    'title'     => IMDBHelper::cleanString($aReturned[2][$i]),
                                    'country'   => IMDBHelper::cleanString($strName)
                                );
                            }
                        }

                        file_put_contents($sCacheFile, serialize($aReturn));

                        return IMDBHelper::arrayOutput(
                            $this->bArrayOutput,
                            $this->sSeparator,
                            $this->sNotFound, $aReturn
                        );
                    }
                }
            }


            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string “Aspect Ratio” or $sNotFound.
         */
        public function getAspectRatio() {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex(
                    $this->sSource,
                    self::IMDB_ASPECT_RATIO, 1
                );

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string The awards of the movie or $sNotFound.
         */
        public function getAwards() {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_AWARDS, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param int  $iLimit How many cast members should be returned?
         * @param bool $bMore  Add … if there are more cast members than printed.
         *
         * @return string A list with cast members or $sNotFound.
         */
        public function getCast($iLimit = 0, $bMore = true)
        {
            if (true === $this->isReady) {
                $aMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_CAST);
                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        if (0 !== $iLimit && $i >= $iLimit) {
                            break;
                        }

                        $aReturn[] = IMDBHelper::cleanString($sName);
                    }

                    $bMore = (0 !== $iLimit && $bMore && (count($aMatch[2]) > $iLimit) ? '…' : '');

                    $bHaveMore = ($bMore && (count($aMatch[2]) > $iLimit));

                    return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, $this->sNotFound, $aReturn, $bHaveMore);
                }
            }

            return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, $this->sNotFound);
        }

        /**
         * @param int    $iLimit  How many cast members should be returned?
         * @param bool   $bMore   Add … if there are more cast members than printed.
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with linked cast members or $sNotFound.
         */
        public function getCastAsUrl($iLimit = 0, $bMore = true, $sTarget = '')
        {
            if (true === $this->isReady) {
                $aMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_CAST);

                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        if (0 !== $iLimit && $i >= $iLimit) {
                            break;
                        }

                        $aReturn[] = '<a href="http://www.imdb.com/name/' . IMDBHelper::cleanString($aMatch[1][$i]) . '/"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($sName) . '</a>';
                    }

                    $bHaveMore = ($bMore && (count($aMatch[2]) > $iLimit));

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn,
                        $bHaveMore
                    );
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param int  $iLimit How many cast members should be returned?
         * @param bool $bMore  Add … if there are more cast members than printed.
         *
         * @return string  A list with cast members and their character or
         *                 $sNotFound.
         */
        public function getCastAndCharacter($iLimit = 0, $bMore = true)
        {
            if (true === $this->isReady) {
                $aMatch     = IMDBHelper::matchRegex($this->sSource, self::IMDB_CAST);
                $aMatchChar = IMDBHelper::matchRegex($this->sSource, self::IMDB_CHAR);

                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        if (0 !== $iLimit && $i >= $iLimit) {
                            break;
                        }

                        $aReturn[] = IMDBHelper::cleanString($sName) . ' as ' . IMDBHelper::cleanString($aMatchChar[1][$i]);
                    }

                    $bHaveMore = ($bMore && (count($aMatch[2]) > $iLimit));

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn,
                        $bHaveMore
                    );

                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @param int    $iLimit  How many cast members should be returned?
         * @param bool   $bMore   Add … if there are more cast members than
         *                        printed.
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with linked cast members and their character or
         *                $sNotFound.
         */
        public function getCastAndCharacterAsUrl($iLimit = 0, $bMore = true, $sTarget = '')
        {
            if (true === $this->isReady) {
                $aMatch     = IMDBHelper::matchRegex($this->sSource, self::IMDB_CAST);
                $aMatchChar = IMDBHelper::matchRegex($this->sSource, self::IMDB_CHAR);

                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        if (0 !== $iLimit && $i >= $iLimit) {
                            break;
                        }

                        $aReturn[] = '<a href="http://www.imdb.com/name/' . IMDBHelper::cleanString($aMatch[1][$i]) . '/"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($sName) . '</a> as ' . IMDBHelper::cleanString($aMatchChar[1][$i]);
                    }

                    $bHaveMore = ($bMore && (count($aMatch[2]) > $iLimit));

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn,
                        $bHaveMore
                    );

                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string The certification of the movie or $sNotFound.
         */
        public function getCertification()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_CERTIFICATION, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string Color or $sNotFound.
         */
        public function getColor()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_COLOR, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string The company producing the movie or $sNotFound.
         */
        public function getCompany()
        {
            if (true === $this->isReady) {
                $sMatch = $this->getCompanyAsUrl();

                if ($this->sNotFound !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sTarget Add a target to the links?
         *
         * @return string The linked company producing the movie or $sNotFound.
         */
        public function getCompanyAsUrl($sTarget = '')
        {
            if (true === $this->isReady) {
                $aMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_COMPANY);

                if (isset($aMatch[2][0])) {
                    return '<a href="http://www.imdb.com/company/' . IMDBHelper::cleanString($aMatch[1][0]) . '/"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($aMatch[2][0]) . '</a>';
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string A list with countries or $sNotFound.
         */
        public function getCountry()
        {
            if (true === $this->isReady) {
                $sMatch = $this->getCountryAsUrl();

                if ($this->sNotFound !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with linked countries or $sNotFound.
         */
        public function getCountryAsUrl($sTarget = '')
        {
            if (true === $this->isReady) {
                $aMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_COUNTRY);

                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        $aReturn[] = '<a href="http://www.imdb.com/country/' . trim($aMatch[1][$i]) . '/"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($sName) . '</a>';
                    }

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn
                    );
                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string A list with the creators or $sNotFound.
         */
        public function getCreator()
        {
            if (true === $this->isReady) {
                $sMatch = $this->getCreatorAsUrl();

                if ($this->sNotFound !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with the linked creators or $sNotFound.
         */
        public function getCreatorAsUrl($sTarget = '')
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_CREATOR, 1);
                $aMatch = IMDBHelper::matchRegex($sMatch, self::IMDB_NAME);

                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        $aReturn[] = '<a href="http://www.imdb.com/name/' . IMDBHelper::cleanString($aMatch[1][$i]) . '/"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($sName) . '</a>';
                    }

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn
                    );
                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string A list with the directors or $sNotFound.
         */
        public function getDirector()
        {
            if (true === $this->isReady) {
                $sMatch = $this->getDirectorAsUrl();

                if ($this->sNotFound !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with the linked directors or $sNotFound.
         */
        public function getDirectorAsUrl($sTarget = '')
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_DIRECTOR, 1);
                $aMatch = IMDBHelper::matchRegex($sMatch, self::IMDB_NAME);

                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        $aReturn[] = '<a href="http://www.imdb.com/name/' . IMDBHelper::cleanString($aMatch[1][$i]) . '/"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($sName) . '</a>';
                    }

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn
                    );
                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string A list with the genres or $sNotFound.
         */
        public function getGenre()
        {
            if (true === $this->isReady) {
                $sMatch = $this->getGenreAsUrl();

                if ($this->sNotFound !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with the linked genres or $sNotFound.
         */
        public function getGenreAsUrl($sTarget = '')
        {
            if (true === $this->isReady) {
                $aMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_GENRE);
                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        $aReturn[] = '<a href="http://www.imdb.com/Sections/Genres/' . IMDBHelper::cleanString($aMatch[1][$i]) . '/"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($sName) . '</a>';
                    }

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn
                    );
                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string A list with the languages or $sNotFound.
         */
        public function getLanguage()
        {
            if (true === $this->isReady) {
                $sMatch = $this->getLanguageAsUrl();

                if ($this->sNotFound !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with the linked languages or $sNotFound.
         */
        public function getLanguageAsUrl($sTarget = '')
        {
            if (true === $this->isReady) {
                $aMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_LANGUAGE);
                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        $aReturn[] = '<a href="http://www.imdb.com/language/' . IMDBHelper::cleanString($aMatch[1][$i]) . '"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($sName) . '</a>';
                    }

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn
                    );
                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string A list with the location or $sNotFound.
         */
        public function getLocation()
        {
            if (true === $this->isReady) {
                $sMatch = $this->getLocationAsUrl();
                if ($this->sNotFound !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with the linked location or $sNotFound.
         */
        public function getLocationAsUrl($sTarget = '')
        {
            if (true === $this->isReady) {
                $aMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_LOCATION);
                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        $aReturn[] = '<a href="http://www.imdb.com/search/title?locations=' . IMDBHelper::cleanString($aMatch[1][$i]) . '"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($sName) . '</a>';
                    }

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn
                    );
                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string The MOVIEmeter of the movie or $sNotFound.
         */
        public function getMovieMeter()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_MOVIEMETER, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string The MPAA of the movie or $sNotFound.
         */
        public function getMpaa()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_MPAA, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string A list with the plot keywords or $sNotFound.
         */
        public function getPlotKeywords()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_PLOT_KEYWORDS, 1);
                if (false !== $sMatch) {
                    $aReturn = explode('|', IMDBHelper::cleanString($sMatch));

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound, $aReturn
                    );
                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string The plot of the movie or $sNotFound.
         */
        public function getPlot($iLimit = 0)
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_PLOT, 1);

                if (false !== $sMatch) {
                    if ($iLimit !== 0) {
                        return IMDBHelper::getShortText(IMDBHelper::cleanString($sMatch), $iLimit);
                    }

                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sSize     Small or big poster?
         * @param bool   $bDownload Return URL to the poster or download it?
         *
         * @return bool|string Path to the poster.
         */
        public function getPoster($sSize = 'small', $bDownload = true)
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_POSTER, 1);

                if (false !== $sMatch) {
                    if ('big' === strtolower($sSize) && false !== strstr($sMatch, '@._')) {
                        $sMatch = substr($sMatch, 0, strpos($sMatch, '@._')) . '@.jpg';
                    }

                    if (false === $bDownload) {
                        return IMDBHelper::cleanString($sMatch);
                    } else {
                        $sLocal = IMDBHelper::saveImage($sMatch, $this->iId);
                        if (file_exists($sLocal)) {
                            return $sLocal;
                        } else {
                            return $sMatch;
                        }
                    }
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string The rating of the movie or $sNotFound.
         */
        public function getRating()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_RATING, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string The release date of the movie or $sNotFound.
         */
        public function getReleaseDate()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_RELEASE_DATE, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }


        /**
        * Release date doesn't contain all the information we need to create a media and
        * we need this function that checks if users can vote target media (if can, it's released).
        *
        * @return  true If the media is released
        */
        public function isReleased()
        {
            $strReturn = $this->getReleaseDate();

            if ($strReturn == $this->sNotFound || $strReturn == 'Not yet released') {
                return false;
            }

            return true;
        }


        /**
         * @return string The runtime of the movie or $sNotFound.
         */
        public function getRuntime()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_RUNTIME, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string A list with the seasons or $sNotFound.
         */
        public function getSeasons()
        {
            if (true === $this->isReady) {
                $sMatch = $this->getSeasonsAsUrl();

                if ($this->sNotFound !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with the linked seasons or $sNotFound.
         */
        public function getSeasonsAsUrl($sTarget = '')
        {
            if (true === $this->isReady) {
                $aMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_SEASONS);

                if (count($aMatch[1])) {
                    foreach ($aMatch[1] as $i => $sName) {
                        $aReturn[] = '<a href="http://www.imdb.com/title/tt' . $this->iId . '/episodes?season=' . $sName . '"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . $sName . '</a>';
                    }

                    return IMDBHelper::arrayOutput(
                        $this->bArrayOutput,
                        $this->sSeparator,
                        $this->sNotFound,
                        $aReturn
                    );
                }
            }

            return IMDBHelper::arrayOutput(
                $this->bArrayOutput,
                $this->sSeparator,
                $this->sNotFound
            );
        }

        /**
         * @return string The sound mix of the movie or $sNotFound.
         */
        public function getSoundMix()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_SOUND_MIX, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string The tagline of the movie or $sNotFound.
         */
        public function getTagline()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_TAGLINE, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param bool $bForceLocal Try to return the original name of the movie.
         *
         * @return string The title of the movie or $sNotFound.
         */
        public function getTitle($bForceLocal = false)
        {
            if (true === $this->isReady) {
                if (true === $bForceLocal) {
                    $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_TITLE_ORIG, 1);

                    if (false !== $sMatch && "" !== $sMatch) {
                        return IMDBHelper::cleanString($sMatch);
                    }
                }

                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_TITLE, 1);
                $sMatch = preg_replace('~\(\d{4}\)$~Ui', '', $sMatch);

                if (false !== $sMatch && "" !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param bool $bEmbed Link to player directly?
         *
         * @return string The URL to the trailer of the movie or $sNotFound.
         */
        public function getTrailerAsUrl($bEmbed = false)
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_TRAILER, 1);

                if (false !== $sMatch) {
                    $sUrl = 'http://www.imdb.com/video/imdb/' . $sMatch . '/' . ($bEmbed ? 'player' : '');

                    return IMDBHelper::cleanString($sUrl);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string The IMDb URL.
         */
        public function getUrl()
        {
            if (true === $this->isReady) {
                return IMDBHelper::cleanString(str_replace('combined', '', $this->sUrl));
            }

            return $this->sNotFound;
        }

        /**
         * @return string The user review of the movie or $sNotFound.
         */
        public function getUserReview()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_USER_REVIEW, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string The votes of the movie or $sNotFound.
         */
        public function getVotes()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_VOTES, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string A list with the writers or $sNotFound.
         */
        public function getWriter()
        {
            if (true === $this->isReady) {
                $sMatch = $this->getWriterAsUrl();

                if ($this->sNotFound !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @param string $sTarget Add a target to the links?
         *
         * @return string A list with the linked writers or $sNotFound.
         */
        public function getWriterAsUrl($sTarget = '')
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_WRITER, 1);
                $aMatch = IMDBHelper::matchRegex($sMatch, self::IMDB_NAME);

                if (count($aMatch[2])) {
                    foreach ($aMatch[2] as $i => $sName) {
                        $aReturn[] = '<a href="http://www.imdb.com/name/' . IMDBHelper::cleanString($aMatch[1][$i]) . '/"' . ($sTarget ? ' target="' . $sTarget . '"' : '') . '>' . IMDBHelper::cleanString($sName) . '</a>';
                    }

                    return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, $this->sNotFound, $aReturn);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return string The year of the movie or $sNotFound.
         */
        public function getYear()
        {
            if (true === $this->isReady) {
                $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_YEAR, 1);

                if (false !== $sMatch) {
                    return IMDBHelper::cleanString($sMatch);
                }
            }

            return $this->sNotFound;
        }

        /**
         * @return array All data.
         */
        public function getAll()
        {
            $aData                           = array();
            $aData['Aka']                    = array(
                'name'  => 'Also Known As',
                'value' => $this->getAka()
            );

            $aData['Akas']                    = array(
                'name'  => '(all) Also Known As',
                'value' => $this->getAkas()
            );

            $aData['AspectRatio']            = array(
                'name'  => 'Aspect Ratio',
                'value' => $this->getAspectRatio()
            );

            $aData['Awards']                 = array(
                'name'  => 'Awards',
                'value' => $this->getAwards()
            );

            $aData['CastLinked']             = array(
                'name'  => 'Cast',
                'value' => $this->getCastAsUrl()
            );

            $aData['Cast']                   = array(
                'name'  => 'Cast',
                'value' => $this->getCast()
            );

            $aData['CastAndCharacterLinked'] = array(
                'name'  => 'Cast and Character',
                'value' => $this->getCastAndCharacterAsUrl()
            );

            $aData['CastAndCharacter']       = array(
                'name'  => 'Cast and Character',
                'value' => $this->getCastAndCharacter()
            );

            $aData['Certification']          = array(
                'name'  => 'Certification',
                'value' => $this->getCertification()
            );

            $aData['Color']                  = array(
                'name'  => 'Color',
                'value' => $this->getColor()
            );

            $aData['CompanyLinked']          = array(
                'name'  => 'Company',
                'value' => $this->getCompanyAsUrl()
            );

            $aData['Company']                = array(
                'name'  => 'Company',
                'value' => $this->getCompany()
            );

            $aData['CountryLinked']          = array(
                'name'  => 'Country',
                'value' => $this->getCountryAsUrl()
            );

            $aData['Country']                = array(
                'name'  => 'Country',
                'value' => $this->getCountry()
            );

            $aData['CreatorLinked']          = array(
                'name'  => 'Creator',
                'value' => $this->getCreatorAsUrl()
            );

            $aData['Creator']                = array(
                'name'  => 'Creator',
                'value' => $this->getCreator()
            );

            $aData['DirectorLinked']         = array(
                'name'  => 'Director',
                'value' => $this->getDirectorAsUrl()
            );

            $aData['Director']               = array(
                'name'  => 'Director',
                'value' => $this->getDirector()
            );

            $aData['GenreLinked']            = array(
                'name'  => 'Genre',
                'value' => $this->getGenreAsUrl()
            );

            $aData['Genre']                  = array(
                'name'  => 'Genre',
                'value' => $this->getGenre()
            );

            $aData['LanguageLinked']         = array(
                'name'  => 'Language',
                'value' => $this->getLanguageAsUrl()
            );

            $aData['Language']               = array(
                'name'  => 'Language',
                'value' => $this->getLanguage()
            );

            $aData['LocationLinked']         = array(
                'name'  => 'Location',
                'value' => $this->getLocationAsUrl()
            );

            $aData['Location']               = array(
                'name'  => 'Location',
                'value' => $this->getLocation()
            );

            $aData['MovieMeter']             = array(
                'name'  => 'MOVIEmeter',
                'value' => $this->getMovieMeter()
            );

            $aData['MPAA']                   = array(
                'name'  => 'MPAA',
                'value' => $this->getMpaa()
            );

            $aData['PlotKeywords']           = array(
                'name'  => 'Plot Keywords',
                'value' => $this->getPlotKeywords()
            );

            $aData['Plot']                   = array(
                'name'  => 'Plot',
                'value' => $this->getPlot()
            );

            $aData['Poster']                 = array(
                'name'  => 'Poster',
                'value' => $this->getPoster('big')
            );

            $aData['Rating']                 = array(
                'name'  => 'Rating',
                'value' => $this->getRating()
            );

            $aData['ReleaseDate']            = array(
                'name'  => 'Release Date',
                'value' => $this->getReleaseDate()
            );

            $aData['IsReleased']            = array(
                'name'  => 'Is released',
                'value' => $this->isReleased()
            );

            $aData['Runtime']                = array(
                'name'  => 'Runtime',
                'value' => $this->getRuntime()
            );

            $aData['SeasonsLinked']          = array(
                'name'  => 'Seasons',
                'value' => $this->getSeasonsAsUrl()
            );

            $aData['Seasons']                = array(
                'name'  => 'Seasons',
                'value' => $this->getSeasons()
            );

            $aData['SoundMix']               = array(
                'name'  => 'Sound Mix',
                'value' => $this->getSoundMix()
            );

            $aData['Tagline']                = array(
                'name'  => 'Tagline',
                'value' => $this->getTagline()
            );

            $aData['Title']                  = array(
                'name'  => 'Title',
                'value' => $this->getTitle()
            );

            $aData['TrailerLinked']          = array(
                'name'  => 'Trailer',
                'value' => $this->getTrailerAsUrl()
            );

            $aData['Url']                    = array(
                'name'  => 'Url',
                'value' => $this->getUrl()
            );

            $aData['UserReview']             = array(
                'name'  => 'User Review',
                'value' => $this->getUserReview()
            );

            $aData['Votes']                  = array(
                'name'  => 'Votes',
                'value' => $this->getVotes()
            );

            $aData['WriterLinked']           = array(
                'name'  => 'Writer',
                'value' => $this->getWriterAsUrl()
            );

            $aData['Writer']                 = array(
                'name'  => 'Writer',
                'value' => $this->getWriter()
            );

            $aData['Year']                   = array(
                'name'  => 'Year',
                'value' => $this->getYear()
            );

            array_multisort($aData);

            return $aData;
        }
    }
