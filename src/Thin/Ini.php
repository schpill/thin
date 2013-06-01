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
                    $this->Keys[trim($curKey)] = trim($curValue);
                } else {
                    $nKeys[trim($curKey)] = trim($curValue);
                }
                $counter++;
            }

            if($saveInClass) {
                return $this->keysWithoutSections;
            } else {
                return $nKeys;
            }
        }

        public function parseIniWithSections($saveInClass = false)
        {
            $fileHandle = file($this->iniFile);

            $countLines = count($fileHandle);
            $counter = 0;

            $lastSection = "";

            $nKeys = "";

            if ($this->safeFile) {
                $countLines -= 2;
                $counter += 2;
            }

            while ($counter < $countLines) {
                $curLine = $fileHandle[$counter];

                if (strpos($curLine, "[") == 1) {
                    $lastSection = $curLine;
                    continue;
                }

                $explosion = explode("=", $curLine);

                $curKey = trim($explosion[0]);
                $curValue = trim($explosion[1]);

                if ($saveInClass) {
                    $this->keysWithSections[$lastSection][$curKey] = $curValue;
                } else {
                    $nKeys[$lastSection][$curKey] = $curValue;
                }
            }

            if (true === $saveInClass) {
                return $this->keysWithSections;
            } else
                return $nKeys;
            }
        }
