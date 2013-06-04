<?php
    /**
     * Parse Ini File class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Ini
    {
        private $iniFile;
        private $safeFile;
        private $parseClasses;

        public $keysWithoutSections;
        public $keysWithSections;

        /**
         * String that separates nesting levels of configuration data identifiers
         *
         * @var string
         */
        protected $_nestSeparator = '.';

        /**
         * String that separates the parent section name
         *
         * @var string
         */
        protected $_sectionSeparator = ':';

        /**
         * Whether to skip extends or not
         *
         * @var boolean
         */
        protected $_skipExtends = false;


        public function __construct($fileName, $safeFile = false)
        {
            $this->iniFile = $fileName;
            $this->safeFile = $safeFile;
        }

        public function parseIni($saveInClass = true)
        {
            $fileHandle = file($this->iniFile);

            $countLines = count($fileHandle);
            $counter = 0;

            $nKeys = "";

            if ($this->safeFile) {
                $counter += 2;
                $countLines -= 2;
            }

            while ($counter < $countLines) {
                $curLine = $fileHandle[$counter];

                $curLineSplit = explode("=", $curLine);

                $curKey = $curLineSplit[0];
                $curValue = $curLineSplit[1];
                if($saveInClass) {
                    $this->keysWithoutSections[trim($curKey)] = trim($curValue);
                } else {
                    $nKeys[trim($curKey)] = trim($curValue);
                }
                $counter++;
            }

            if(true === $saveInClass) {
                return $this->keysWithoutSections;
            } else {
                return $nKeys;
            }
        }

        public function parseIniWithSections($saveInClass = false)
        {
            $loaded = parse_ini_file($this->iniFile, true);
            dieDump($loaded);
            $iniArray = array();
            foreach ($loaded as $key => $data) {
                $pieces = explode($this->_sectionSeparator, $key);
                $thisSection = trim(current($pieces));
                switch (count($pieces)) {
                    case 1:
                        $iniArray[$thisSection] = $data;
                        break;
                    case 2:
                        $extendedSection = trim(end($pieces));
                        $iniArray[$thisSection] = array_merge(array(';extends' => $extendedSection), $data);
                        break;
                    default:
                        throw new Exception("Section '$thisSection' may not extend multiple sections in $this->iniFile");
                }
            }
            dieDump($iniArray);
            return $iniArray;
        }
