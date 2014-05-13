<?php
    namespace Thin;
    class Vertica
    {
        protected $_link;
        protected $_debug;
        protected $_logs = array();

        const CONNECTION_FAILED = 100;
        const QUERY_FAILED      = 110;
        const PREPARE_FAILED    = 110;

        public function __construct($debug = false)
        {
            $this->_lnk = @odbc_connect(
                'Driver=' . VERTICA_DRIVER . ';Servername=' . VERTICA_SERVER . ';Database=' . VERTICA_DATABASE,
                VERTICA_USER_ETL,
                VERTICA_PASS_ETL
                );
            $this->_debug = (bool) $debug;
        }

        public function autocommit($onOff)
        {
            odbc_autocommit($this->_lnk, $onOff);
        }

        /**
         * Execute a query and return the results
         * @param string $query
         * @param array $params
         * @param bool $fetchResult Return a ressource or a an array
         * @return resource|array
         * @throws Exception
         */
        public function query($query, $params = null, $fetchResult = false)
        {
            $this->checkConnection(true);
            $this->log('Query: ' . $query);

            if(!empty($params)) {
                $this->log('Params: ' . print_r($params, true));
            }
            $start =  microtime(true);

            if(empty($params)) {
                $res = $this->executeQuery($query);
            } else {
                $res = $this->executePreparedStatement($query, $params);
            }

            $end = microtime(true);
            $this->log("Execution time: " . ($end - $start) . " seconds");


            if($fetchResult) {
    	        $this->log('Num Rows: '.odbc_num_rows($res));
                $resutlSet = $this->getRows($res);
                odbc_free_result($res);
                $res = $resutlSet;
                $resultSet = null;
                $fetch = microtime(true);
                $this->log("Fetch time: " . ($fetch - $end) . " seconds");
            }
            return $res;
        }

        /**
         * Execute a query and returns an ODBC result identifier
         * @param string $query
         * @return resource
         * @throws Exception
         */
        protected function executeQuery($query)
        {
            $res = @odbc_exec($this->_lnk, $query);
            if(!$res) {
                $error = odbc_errormsg($this->_lnk);
                $this->log('Query failed: '.$error);
                throw new Exception('Executing query failed ' . $error, self::QUERY_FAILED);
            }
            return $res;
        }

        /**
         * Prepare a query, execute it and return an ODBC result identifier
         * @param string $query
         * @param array $params
         * @return bool|resource
         * @throws Exception
         */
        protected function executePreparedStatement($query, $params)
        {
            $res = odbc_prepare($this->_lnk, $query);
            if(!$res) {
                $error = odbc_errormsg($this->_lnk);
                $this->log('Prepare failed: ' . $error);
                throw new Exception('Preparing query failed ' . $error, self::PREPARE_FAILED);
            }
            $res = odbc_execute($res, $params);
            if(!$res) {
                $error = odbc_errormsg($this->_lnk);
                $this->log('Prepared query execution failed: ' . $error);
                throw new Exception('Executing prepared query failed ' . $error, self::QUERY_FAILED);
            }
            return $res;
        }

        /**
         * Fetch row and return it as an array
         * @param resource $res
         * @return array
         */
        protected function getRows($res)
        {
            $rows = array();
            while ($row = odbc_fetch_array($res)) {
                $rows[] = $row;
            }
            return $rows;
        }

        /**
         * Check if the connection failed and try to reconnect if asked
         * @param bool $reconnect
         * @throws Exception
         */
        protected function checkConnection($reconnect = false)
        {
            if(empty($this->_lnk)) {
                $this->log('CheckConnection: link is not valid');
                if($reconnect) {
                    $this->log('CheckConnection: try to reconnect');
                    $this->_lnk = @odbc_connect('Driver=' . VERTICA_DRIVER . ';Servername=' . VERTICA_SERVER . ';Database='.VERTICA_DATABASE, VERTICA_USER_ETL, VERTICA_PASS_ETL);
                    if(!$this->_lnk) {
                        $this->log('CheckConnection: reconnect failed');
                        throw new Exception('Connection failed or gone away and can\'t reconnect - '.odbc_errormsg($this->_link), self::CONNECTION_FAILED);
                    }
                    $this->log('CheckConnection: reconnected!');
                    return;
                }
                throw new Exception('Connection failed or gone away - ' . odbc_errormsg($this->_link), self::CONNECTION_FAILED);
            }
        }

        protected function log($string)
        {
            if(true === $this->_debug) {
                $this->_logs[] = date('d/m/Y H:i:s') . ' => ' . $string;
            }
            return $this;
        }

        public function getLogs()
        {
            return $this->_logs;
        }
    }
