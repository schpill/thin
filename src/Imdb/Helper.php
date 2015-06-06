<?php
    namespace Thin\Imdb;

    class Helper extends \Thin\Imdb
    {
        /**
         * Regular expression helper.
         *
         * @param string $sContent The content to search in.
         * @param string $sPattern The regular expression.
         * @param null   $iIndex   The index to return.
         *
         * @return bool   If no match was found.
         * @return string If one match was found.
         * @return array  If more than one match was found.
         */
        public static function matchRegex($sContent, $sPattern, $iIndex = null)
        {
            preg_match_all($sPattern, $sContent, $aMatches);

            if ($aMatches === false) {
                return false;
            }

            if ($iIndex !== null && is_int($iIndex)) {
                if (isset($aMatches[$iIndex][0])) {
                    return $aMatches[$iIndex][0];
                }

                return false;
            }

            return $aMatches;
        }

        /**
         * Prefered output in responses with multiple elements
         *
         * @param bool $bArrayOutput Native array or string wtih separators.
         * @param string $sSeparator String separator.
         * @param string $sNotFound Not found text.
         * @param array $aReturn Original input.
         * @param bool $bHaveMore Have more elements indicator.
         *
         * @return string Multiple results separeted by selected separator string.
         * @return array  Multiple results enclosed into native array.
         */
        public static function arrayOutput($bArrayOutput, $sSeparator, $sNotFound, $aReturn = null, $bHaveMore = false)
        {
            if ($bArrayOutput){
                if ($aReturn == null || !is_array($aReturn)) {
                    return array();
                }

                if ($bHaveMore) {
                    $aReturn[] = '…';
                }

                return $aReturn;
            } else {
                if ($aReturn == null || !is_array($aReturn)) {
                    return $sNotFound;
                }

                foreach ($aReturn as $i => $value) {
                    if (is_array($value)) {
                        $aReturn[$i] = implode($sSeparator, $value);
                    }
                }

                return implode($sSeparator, $aReturn) . (($bHaveMore) ? '…' : '');
            }
        }


        /**
         * @param string $sInput Input (eg. HTML).
         *
         * @return string Cleaned string.
         */
        public static function cleanString($sInput)
        {
            $aSearch  = array(
                'Full summary &raquo;',
                'Full synopsis &raquo;',
                'Add summary &raquo;',
                'Add synopsis &raquo;',
                'See more &raquo;',
                'See why on IMDbPro.'
            );

            $aReplace = array(
                '',
                '',
                '',
                '',
                '',
                ''
            );

            $sInput   = strip_tags($sInput);
            $sInput   = str_replace('&nbsp;', ' ', $sInput);
            $sInput   = str_replace($aSearch, $aReplace, $sInput);
            $sInput   = html_entity_decode($sInput, ENT_QUOTES | ENT_HTML5);

            if (mb_substr($sInput, -3) === ' | ') {
                $sInput = mb_substr($sInput, 0, -3);
            }

            return trim($sInput);
        }

        /**
         * @param string $sText   The long text.
         * @param int    $iLength The maximum length of the text.
         *
         * @return string The shortened text.
         */
        public static function getShortText($sText, $iLength = 100)
        {
            if (mb_strlen($sText) <= $iLength) {
                return $sText;
            }

            list($sShort) = explode(
                "\n",
                wordwrap(
                    $sText,
                    $iLength - 1
                )
            );

            if (substr($sShort, -1) !== '.') {
                return $sShort . '…';
            }

            return $sShort;
        }

        /**
         * @param string $sUrl      The URL to fetch.
         * @param bool   $bDownload Download?
         *
         * @return bool|mixed Array on success, false on failure.
         */
        public static function runCurl($sUrl, $bDownload = false)
        {
            $oCurl = curl_init($sUrl);

            curl_setopt_array($oCurl, array(
                CURLOPT_BINARYTRANSFER => ($bDownload ? true : false),
                CURLOPT_CONNECTTIMEOUT => self::IMDB_TIMEOUT,
                CURLOPT_ENCODING       => '',
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_FRESH_CONNECT  => true,
                CURLOPT_HEADER         => ($bDownload ? false : true),
                CURLOPT_HTTPHEADER     => array(
                    'Accept-Language:' . self::IMDB_LANG,
                    'Accept-Charset:' . 'utf-8, iso-8859-1;q=0.8',
                ),
                CURLOPT_REFERER        => 'http://www.google.com',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::IMDB_TIMEOUT,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                CURLOPT_VERBOSE        => false
            ));

            $sOutput   = curl_exec($oCurl);
            $aCurlInfo = curl_getinfo($oCurl);

            curl_close($oCurl);

            $aCurlInfo['contents'] = $sOutput;

            if (200 !== $aCurlInfo['http_code'] && 302 !== $aCurlInfo['http_code']) {
                if (true === self::IMDB_DEBUG) {
                    echo '<pre><b>cURL returned wrong HTTP code “' . $aCurlInfo['http_code'] . '”, aborting.</b></pre>';
                }

                return false;
            }

            return $aCurlInfo;
        }

        /**
         * @param $sUrl The URL to the image to download.
         * @param $iId  The ID of the movie.
         *
         * @return string Local path.
         */
        public static function saveImage($sUrl, $iId)
        {
            if (preg_match('~title_addposter.jpg|imdb-share-logo.png~', $sUrl)) {
                return 'posters/not-found.jpg';
            }

            $sRoot = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__FILE__);

            $sFilename = $sRoot . '/posters/' . $iId . '.jpg';

            if (file_exists($sFilename)) {
                return 'posters/' . $iId . '.jpg';
            }

            $aCurlInfo = self::runCurl($sUrl, true);
            $sData     = $aCurlInfo['contents'];

            if (false === $sData) {
                return 'posters/not-found.jpg';
            }

            $oFile = fopen($sFilename, 'x');
            fwrite($oFile, $sData);
            fclose($oFile);

            return 'posters/' . $iId . '.jpg';
        }
    }
