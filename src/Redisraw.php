<?php
    /**
     * Redis class
     * @author      Gerald Plusquellec
     */

    namespace Thin;
    class Redisraw
    {
        /**
         * The address for the Redis host.
         *
         * @var string
         */
        protected $host;

        /**
         * The port on which Redis can be accessed on the host.
         *
         * @var int
         */
        protected $port;

        /**
         * The databse number the connection selects on load.
         *
         * @var int
         */
        protected $database;

        /**
         * The connection to the Redis database.
         *
         * @var resource
         */
        protected $connection;

        /**
         * The active Redis database instances multiton.
         *
         * @var array
         */
        protected static $databases = array();

        /**
         * Create a new Redis connection instance.
         *
         * @param  string  $host
         * @param  string  $port
         * @param  int     $database
         * @return void
         */
        public function __construct($host = '127.0.0.1', $port = 6379, $database = 0)
        {
            $this->host = $host;
            $this->port = $port;
            $this->database = $database;
            $this->connect();
        }

        /**
         * Get a Redis database connection instance.
         *
         * The given name should correspond to a Redis database in the configuration file.
         *
         * <code>
         *      // Get the default Redis database instance
         *      $redis = Redis::db();
         *
         *      // Get a specified Redis database instance
         *      $reids = Redis::db('redis_2');
         * </code>
         *
         * @param  string  $name
         * @return Redis
         */
        public static function db($connectionInfos, $name = 'default')
        {
            if (!isset(static::$databases[$name])) {
                extract($connectionInfos);
                static::$databases[$name] = new self($host, $port, $database);
            }
            return static::$databases[$name];
        }

        /**
         * Execute a command against the Redis database.
         *
         * <code>
         *      // Execute the GET command for the "name" key
         *      $name = Redis::db()->run('get', array('name'));
         *
         *      // Execute the LRANGE command for the "list" key
         *      $list = Redis::db()->run('lrange', array(0, 5));
         * </code>
         *
         * @param  string  $method
         * @param  array   $parameters
         * @return mixed
         */
        public function run($method, $parameters)
        {dieDump($method);
            fwrite($this->connect(), $this->command($method, (array) $parameters));
            $response = trim(fgets($this->connect()));
            return $this->parse($response);
        }

        /**
         * Parse and return the response from the Redis database.
         *
         * @param  string  $response
         * @return mixed
         */
        protected function parse($response)
        {
            switch (substr($response, 0, 1)) {
                case '-':
                    throw new Exception('Redis error: ' . substr(trim($response), 4));

                case '+':
                case ':':
                    return $this->inline($response);

                case '$':
                    return $this->bulk($response);

                case '*':
                    return $this->multibulk($response);

                default:
                    throw new Exception("Unknown Redis response: " . substr($response, 0, 1));
            }
        }

        /**
         * Establish the connection to the Redis database.
         *
         * @return resource
         */
        protected function connect()
        {
            if (!is_null($this->connection)) {
                return $this->connection;
            }
            // $this->connection = fsockopen($this->host, $this->port, $error, $message);
            $remote_socket = 'tcp://'.$this->host.':' . $this->port;
            $this->connection = @stream_socket_client($remote_socket, $errno, $error, 30);
            if ($this->connection === false) {
                throw new Exception("Error making Redis connection: {$error} - {$message}");
            }
            // $this->select($this->database);
            return $this->connection;
        }

        /**
         * Build the Redis command based from a given method and parameters.
         *
         * Redis protocol states that a command should conform to the following format:
         *
         *     *<number of arguments> CR LF
         *     $<number of bytes of argument 1> CR LF
         *     <argument data> CR LF
         *     ...
         *     $<number of bytes of argument N> CR LF
         *     <argument data> CR LF
         *
         * More information regarding the Redis protocol: http://redis.io/topics/protocol
         *
         * @param  string  $method
         * @param  array   $parameters
         * @return string
         */
        protected function command($method, $parameters)
        {
            $command  = '*' . (count($parameters) + 1) . "\n";
            $command .= '$' . strlen($method) . "\n";
            $command .= Inflector::upper($method) . "\n";
            foreach ($parameters as $parameter) {
                $command .= '$' . strlen($parameter) . "\n" . $parameter . "\n";
            }
            return $command;
        }

        /**
         * Parse and handle an inline response from the Redis database.
         *
         * @param  string  $response
         * @return string
         */
        protected function inline($response)
        {
            return substr(trim($response), 1);
        }

        /**
         * Parse and handle a bulk response from the Redis database.
         *
         * @param  string  $head
         * @return string
         */
        protected function bulk($head)
        {
            if ($head == '$-1') {
                return;
            }
            list($read, $response, $size) = array(0, '', substr($head, 1));
            if ($size > 0) {
                do {
                    // Calculate and read the appropriate bytes off of the Redis response.
                    // We'll read off the response in 1024 byte chunks until the entire
                    // response has been read from the database.
                    $block = (($remaining = $size - $read) < 1024) ? $remaining : 1024;
                    $response .= fread($this->connection, $block);
                    $read += $block;
                } while ($read < $size);
            }
            // The response ends with a trailing CRLF. So, we need to read that off
            // of the end of the file stream to get it out of the way of the next
            // command that is issued to the database.
            fread($this->connection, 2);
            return $response;
        }

        /**
         * Parse and handle a multi-bulk reply from the Redis database.
         *
         * @param  string  $head
         * @return array
         */
        protected function multibulk($head)
        {
            if (($count = substr($head, 1)) == '-1') {
                return;
            }
            $response = array();
            // Iterate through each bulk response in the multi-bulk and parse it out
            // using the "parse" method since a multi-bulk response is just a list
            // of plain old Redis database responses.
            for ($i = 0 ; $i < $count ; $i++) {
                $response[] = $this->parse(trim(fgets($this->connection, 512)));
            }
            return $response;
        }

        /**
         * Dynamically make calls to the Redis database.
         */
        public function __call($method, $parameters)
        {
            return $this->run($method, $parameters);
        }

        /**
         * Dynamically pass static method calls to the Redis instance.
         */
        public static function __callStatic($method, $parameters)
        {
            return static::db()->run($method, $parameters);
        }

        /**
         * Close the connection to the Redis database.
         *
         * @return void
         */
        public function __destruct()
        {
            if ($this->connection) {
                fclose($this->connection);
            }
        }

    }
