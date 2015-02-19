<?php
    /**
     * @author Gérald Plusquellec
     */

    namespace Thin;

    class Shell
    {
        /**
         * Path to the Hadoop
         *
         * @var string
         */
        private $configuration;

        /**
         * @param string $hadoopPath Path
         */
        public function __construct($configuration)
        {
            $this->configuration = (array) $configuration;
        }

        /**
         * @return string
         */
        public function getConfiguration()
        {
            return $this->configuration;
        }

        /**
         * @param string $cmd
         * @param array|string $args
         * @return mix
         */
        public function exec($cmd, $args)
        {
            return system("{$this->prepareCmd($cmd)} {$this->prepareCmdArgs($args)}");
        }

        /**
         * @param string $cmd
         * @return string
         */
        private function prepareCmd($cmd)
        {
            $result = (string) $cmd;
            return $result;
        }

        /**
         * @param string|array $args
         * @return string
         */
        private function prepareCmdArgs($args)
        {
            if (!Arrays::isArray($args)) {
                return (string) $args;
            }

            $result = '';
            foreach ($args as $arg => $value) {
                if (!is_int($arg)) {
                    $arg = (string) $arg;
                    $result .= " -$arg";
                }

                $value = (string) $value;
                $result .= " $value";
            }

            return trim($result);
        }
    }
