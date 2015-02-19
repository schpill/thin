<?php
    use Thin\Arrays;
    use Thin\Inflector;
    use Thin\Utils;

    class One implements ArrayAccess
    {

        // ----------------------- //
        // --- CLASS CONSTANTS --- //
        // ----------------------- //

        // WHERE and HAVING condition array keys
        const CONDITION_FRAGMENT    = 0;
        const CONDITION_VALUES      = 1;

        const DEFAULT_CONNECTION    = 'default';

        // Limit clause style
        const LIMIT_STYLE_TOP_N     = "top";
        const LIMIT_STYLE_LIMIT     = "limit";

        // ------------------------ //
        // --- CLASS PROPERTIES --- //
        // ------------------------ //

        // Class configuration
        protected static $_default_config = [
            'connection_string' => 'sqlite::memory:',
            'id_column' => 'id',
            'id_column_overrides' => [],
            'error_mode' => PDO::ERRMODE_EXCEPTION,
            'username' => null,
            'password' => null,
            'driver_options' => null,
            'identifier_quote_character' => null, // if this is null, will be autodetected
            'limit_clause_style' => null, // if this is null, will be autodetected
            'logging' => false,
            'logger' => null,
            'caching' => false,
            'caching_auto_clear' => false,
            'return_result_sets' => false,
        ];

        // Map of configuration settings
        protected static $_config = [];

        // Map of database connections, instances of the PDO class
        protected static $_db = [];

        // Last query run, only populated if logging is enabled
        protected static $_last_query;

        // Log of all queries run, mapped by connection key, only populated if logging is enabled
        protected static $_query_log = [];

        // Query cache, only used if query caching is enabled
        protected static $_query_cache = [];

        // Reference to previously used PDOStatement object to enable low-level access, if needed
        protected static $_lastStatement = null;

        // --------------------------- //
        // --- INSTANCE PROPERTIES --- //
        // --------------------------- //

        // Key name of the connections in self::$_db used by this instance
        protected $_connection_name;

        // The name of the table the current One instance is associated with
        protected $_table_name;

        // Alias for the table to be used in SELECT queries
        protected $_table_alias = null;

        // Values to be bound to the query
        protected $_values = [];

        // Columns to select in the result
        protected $_resultColumns = ['*'];

        // Are we using the default result column or have these been manually changed?
        protected $_using_default_resultColumns = true;

        // Join sources
        protected $_join_sources = [];

        // Should the query include a DISTINCT keyword?
        protected $_distinct = false;

        // Is this a raw query?
        protected $_is_raw_query = false;

        // The raw query
        protected $_raw_query = '';

        // The raw query parameters
        protected $_raw_parameters = [];

        // Array of WHERE clauses
        protected $_where_conditions = [];

        // LIMIT
        protected $_limit = null;

        // OFFSET
        protected $_offset = null;

        // ORDER BY
        protected $_order_by = [];

        // GROUP BY
        protected $_group_by = [];

        // HAVING
        protected $_having_conditions = [];

        // The data for a hydrated instance of the class
        protected $_data = [];

        // Fields that have been modified during the
        // lifetime of the object
        protected $_dirty_fields = [];

        // Fields that are to be inserted in the DB raw
        protected $_expr_fields = [];

        // Is this a new object (has create() been called)?
        protected $_is_new = false;

        // Name of the column to use as the primary key for
        // this instance only. Overrides the config settings.
        protected $_instance_id_column = null;

        // ---------------------- //
        // --- STATIC METHODS --- //
        // ---------------------- //

        /**
         * Pass configuration settings to the class in the fOne of
         * key/value pairs. As a shortcut, if the second argument
         * is omitted and the key is a string, the setting is
         * assumed to be the DSN string used by PDO to connect
         * to the database (often, this will be the only configuration
         * required to use One). If you have more than one setting
         * you wish to configure, another shortcut is to pass an array
         * of settings (and omit the second argument).
         * @param string $key
         * @param mixed $value
         * @param string $connection_name Which connection to use
         */
        public static function configure($key, $value = null, $connection_name = self::DEFAULT_CONNECTION)
        {
            self::_setupDbConfig($connection_name); //ensures at least default config is set

            if (Arrays::is($key)) {
                // Shortcut: If only one array argument is passed,
                // assume it's an array of configuration settings
                foreach ($key as $conf_key => $conf_value) {
                    self::configure($conf_key, $conf_value, $connection_name);
                }
            } else {
                if (is_null($value)) {
                    // Shortcut: If only one string argument is passed,
                    // assume it's a connection string
                    $value = $key;
                    $key = 'connection_string';
                }

                self::$_config[$connection_name][$key] = $value;
            }
        }

        /**
         * Retrieve configuration options by key, or as whole array.
         * @param string $key
         * @param string $connection_name Which connection to use
         */
        public static function get_config($key = null, $connection_name = self::DEFAULT_CONNECTION) {
            if ($key) {
                return self::$_config[$connection_name][$key];
            } else {
                return self::$_config[$connection_name];
            }
        }

        /**
         * Delete all configs in _config array.
         */
        public static function reset_config()
        {
            self::$_config = [];
        }

        /**
         * Despite its slightly odd name, this is actually the factory
         * method used to acquire instances of the class. It is named
         * this way for the sake of a readable interface, ie
         * One::for_table('table_name')->find_one()-> etc. As such,
         * this will nOneally be the first method called in a chain.
         * @param string $table_name
         * @param string $connection_name Which connection to use
         * @return One
         */
        public static function for_table($table_name, $connection_name = self::DEFAULT_CONNECTION)
        {
            self::_setupDb($connection_name);

            return new self($table_name, [], $connection_name);
        }

        /**
         * Set up the database connection used by the class
         * @param string $connection_name Which connection to use
         */
        protected static function _setupDb($connection_name = self::DEFAULT_CONNECTION)
        {
            if (!Arrays::exists($connection_name, self::$_db) || !is_object(self::$_db[$connection_name])) {
                self::_setupDbConfig($connection_name);

                $db = new PDO(
                    self::$_config[$connection_name]['connection_string'],
                    self::$_config[$connection_name]['username'],
                    self::$_config[$connection_name]['password'],
                    self::$_config[$connection_name]['driver_options']
                );

                $db->setAttribute(PDO::ATTR_ERRMODE, self::$_config[$connection_name]['error_mode']);

                self::set_db($db, $connection_name);
            }
        }

       /**
        * Ensures configuration (multiple connections) is at least set to default.
        * @param string $connection_name Which connection to use
        */
        protected static function _setupDbConfig($connection_name)
        {
            if (!Arrays::exists($connection_name, self::$_config)) {
                self::$_config[$connection_name] = self::$_default_config;
            }
        }

        /**
         * Set the PDO object used by One to communicate with the database.
         * This is public in case the One should use a ready-instantiated
         * PDO object as its database connection. Accepts an optional string key
         * to identify the connection if multiple connections are used.
         * @param PDO $db
         * @param string $connection_name Which connection to use
         */
        public static function set_db($db, $connection_name = self::DEFAULT_CONNECTION)
        {
            self::_setupDbConfig($connection_name);
            self::$_db[$connection_name] = $db;

            if(!is_null(self::$_db[$connection_name])) {
                self::_setupIdentifierQuoteCharacter($connection_name);
                self::_setupLimitClauseStyle($connection_name);
            }
        }

        /**
         * Delete all registered PDO objects in _db array.
         */
        public static function reset_db()
        {
            self::$_db = [];
        }

        /**
         * Detect and initialise the character used to quote identifiers
         * (table names, column names etc). If this has been specified
         * manually using One::configure('identifier_quote_character', 'some-char'),
         * this will do nothing.
         * @param string $connection_name Which connection to use
         */
        protected static function _setupIdentifierQuoteCharacter($connection_name)
        {
            if (is_null(self::$_config[$connection_name]['identifier_quote_character'])) {
                self::$_config[$connection_name]['identifier_quote_character'] = self::_detectIdentifierQuoteCharacter($connection_name);
            }
        }

        /**
         * Detect and initialise the limit clause style ("SELECT TOP 5" /
         * "... LIMIT 5"). If this has been specified manually using
         * One::configure('limit_clause_style', 'top'), this will do nothing.
         * @param string $connection_name Which connection to use
         */
        public static function _setupLimitClauseStyle($connection_name)
        {
            if (is_null(self::$_config[$connection_name]['limit_clause_style'])) {
                self::$_config[$connection_name]['limit_clause_style'] = self::_detectLimitClauseStyle($connection_name);
            }
        }

        /**
         * Return the correct character used to quote identifiers (table
         * names, column names etc) by looking at the driver being used by PDO.
         * @param string $connection_name Which connection to use
         * @return string
         */
        protected static function _detectIdentifierQuoteCharacter($connection_name)
        {
            switch(self::get_db($connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME)) {
                case 'pgsql':
                case 'sqlsrv':
                case 'dblib':
                case 'mssql':
                case 'sybase':
                case 'firebird':
                    return '"';
                case 'mysql':
                case 'sqlite':
                case 'sqlite2':
                case 'sqlite3':
                default:
                    return '`';
            }
        }

        /**
         * Returns a constant after determining the appropriate limit clause
         * style
         * @param string $connection_name Which connection to use
         * @return string Limit clause style keyword/constant
         */
        protected static function _detectLimitClauseStyle($connection_name)
        {
            switch(self::get_db($connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME)) {
                case 'sqlsrv':
                case 'dblib':
                case 'mssql':
                    return One::LIMIT_STYLE_TOP_N;
                default:
                    return One::LIMIT_STYLE_LIMIT;
            }
        }

        /**
         * Returns the PDO instance used by the the One to communicate with
         * the database. This can be called if any low-level DB access is
         * required outside the class. If multiple connections are used,
         * accepts an optional key name for the connection.
         * @param string $connection_name Which connection to use
         * @return PDO
         */
        public static function get_db($connection_name = self::DEFAULT_CONNECTION)
        {
            self::_setupDb($connection_name); // required in case this is called before One is instantiated

            return self::$_db[$connection_name];
        }

        /**
         * Executes a raw query as a wrapper for PDOStatement::execute.
         * Useful for queries that can't be accomplished through One,
         * particularly those using engine-specific features.
         * @example raw_execute('SELECT `name`, AVG(`order`) FROM `customer` GROUP BY `name` HAVING AVG(`order`) > 10')
         * @example raw_execute('INSERT OR REPLACE INTO `widget` (`id`, `name`) SELECT `id`, `name` FROM `other_table`')
         * @param string $query The raw SQL query
         * @param array  $parameters Optional bound parameters
         * @param string $connection_name Which connection to use
         * @return bool Success
         */
        public static function raw_execute($query, $parameters = [], $connection_name = self::DEFAULT_CONNECTION)
        {
            self::_setupDb($connection_name);

            return self::_execute($query, $parameters, $connection_name);
        }

        /**
         * Returns the PDOStatement instance last used by any connection wrapped by the One.
         * Useful for access to PDOStatement::rowCount() or error infOneation
         * @return PDOStatement
         */
        public static function get_last_statement()
        {
            return self::$_lastStatement;
        }

       /**
        * Internal helper method for executing statments. Logs queries, and
        * stores statement object in ::_last_statment, accessible publicly
        * through ::get_last_statement()
        * @param string $query
        * @param array $parameters An array of parameters to be bound in to the query
        * @param string $connection_name Which connection to use
        * @return bool Response of PDOStatement::execute()
        */
        protected static function _execute($query, $parameters = [], $connection_name = self::DEFAULT_CONNECTION)
        {
            $statement = self::get_db($connection_name)->prepare($query);
            self::$_lastStatement = $statement;
            $time = microtime(true);

            foreach ($parameters as $key => &$param) {
                if (is_null($param)) {
                    $type = PDO::PARAM_NULL;
                } else if (is_bool($param)) {
                    $type = PDO::PARAM_BOOL;
                } else if (is_int($param)) {
                    $type = PDO::PARAM_INT;
                } else {
                    $type = PDO::PARAM_STR;
                }

                $statement->bindParam(is_int($key) ? ++$key : $key, $param, $type);
            }

            $q = $statement->execute();

            self::_logQuery($query, $parameters, $connection_name, (microtime(true)-$time));

            return $q;
        }

        /**
         * Add a query to the internal query log. Only works if the
         * 'logging' config option is set to true.
         *
         * This works by manually binding the parameters to the query - the
         * query isn't executed like this (PDO nOneally passes the query and
         * parameters to the database which takes care of the binding) but
         * doing it this way makes the logged queries more readable.
         * @param string $query
         * @param array $parameters An array of parameters to be bound in to the query
         * @param string $connection_name Which connection to use
         * @param float $query_time Query time
         * @return bool
         */
        protected static function _logQuery($query, $parameters, $connection_name, $query_time)
        {
            // If logging is not enabled, do nothing
            if (!self::$_config[$connection_name]['logging']) {
                return false;
            }

            if (!isset(self::$_query_log[$connection_name])) {
                self::$_query_log[$connection_name] = [];
            }

            // Strip out any non-integer indexes from the parameters
            foreach($parameters as $key => $value) {
                if (!is_int($key)) unset($parameters[$key]);
            }

            if (count($parameters) > 0) {
                // Escape the parameters
                $parameters = array_map(
                    array(
                        self::get_db($connection_name),
                        'quote'
                    ),
                    $parameters
                );

                // Avoid %fOneat collision for vsprintf
                $query = str_replace("%", "%%", $query);

                // Replace placeholders in the query for vsprintf
                if(false !== strpos($query, "'") || false !== strpos($query, '"')) {
                    $query = OneString::str_replace_outside_quotes("?", "%s", $query);
                } else {
                    $query = str_replace("?", "%s", $query);
                }

                // Replace the question marks in the query with the parameters
                $bound_query = vsprintf($query, $parameters);
            } else {
                $bound_query = $query;
            }

            self::$_last_query = $bound_query;
            self::$_query_log[$connection_name][] = $bound_query;


            if(is_callable(self::$_config[$connection_name]['logger'])){
                $logger = self::$_config[$connection_name]['logger'];
                $logger($bound_query, $query_time);
            }

            return true;
        }

        /**
         * Get the last query executed. Only works if the
         * 'logging' config option is set to true. Otherwise
         * this will return null. Returns last query from all connections if
         * no connection_name is specified
         * @param null|string $connection_name Which connection to use
         * @return string
         */
        public static function get_last_query($connection_name = null)
        {
            if ($connection_name === null) {
                return self::$_last_query;
            }

            if (!isset(self::$_query_log[$connection_name])) {
                return '';
            }

            return end(self::$_query_log[$connection_name]);
        }

        /**
         * Get an array containing all the queries run on a
         * specified connection up to now.
         * Only works if the 'logging' config option is
         * set to true. Otherwise, returned array will be empty.
         * @param string $connection_name Which connection to use
         */
        public static function get_query_log($connection_name = self::DEFAULT_CONNECTION)
        {
            if (isset(self::$_query_log[$connection_name])) {
                return self::$_query_log[$connection_name];
            }

            return [];
        }

        /**
         * Get a list of the available connection names
         * @return array
         */
        public static function get_connection_names()
        {
            return array_keys(self::$_db);
        }

        // ------------------------ //
        // --- INSTANCE METHODS --- //
        // ------------------------ //

        /**
         * "Private" constructor; shouldn't be called directly.
         * Use the One::for_table factory method instead.
         */
        protected function __construct($table_name, $data = [], $connection_name = self::DEFAULT_CONNECTION)
        {
            $this->_table_name = $table_name;
            $this->_data = $data;

            $this->_connection_name = $connection_name;

            self::_setupDbConfig($connection_name);
        }

        /**
         * Create a new, empty instance of the class. Used
         * to add a new row to your database. May optionally
         * be passed an associative array of data to populate
         * the instance. If so, all fields will be flagged as
         * dirty so all will be saved to the database when
         * save() is called.
         */
        public function create($data = null)
        {
            $this->_is_new = true;

            if (!is_null($data)) {
                return $this->hydrate($data)->force_all_dirty();
            }

            return $this;
        }

        /**
         * Specify the ID column to use for this instance or array of instances only.
         * This overrides the id_column and id_column_overrides settings.
         *
         * This is mostly useful for libraries built on top of One, and will
         * not nOneally be used in manually built queries. If you don't know why
         * you would want to use this, you should probably just ignore it.
         */
        public function use_id_column($id_column)
        {
            $this->_instance_id_column = $id_column;

            return $this;
        }

        /**
         * Create an One instance from the given row (an associative
         * array of data fetched from the database)
         */
        protected function _createInstanceFromRow($row)
        {
            $instance = self::for_table($this->_table_name, $this->_connection_name);
            $instance->use_id_column($this->_instance_id_column);
            $instance->hydrate($row);

            return $instance;
        }

        /**
         * Tell the One that you are expecting a single result
         * back from your query, and execute it. Will return
         * a single instance of the One class, or false if no
         * rows were returned.
         * As a shortcut, you may supply an ID as a parameter
         * to this method. This will perfOne a primary key
         * lookup on the table.
         */
        public function find_one($id = null)
        {
            if (!is_null($id)) {
                $this->where_id_is($id);
            }

            $this->limit(1);
            $rows = $this->_run();

            if (empty($rows)) {
                return false;
            }

            return $this->_createInstanceFromRow($rows[0]);
        }

        /**
         * Tell the One that you are expecting multiple results
         * from your query, and execute it. Will return an array
         * of instances of the One class, or an empty array if
         * no rows were returned.
         * @return array|\OneResultSet
         */
        public function find_many()
        {
            if (self::$_config[$this->_connection_name]['return_result_sets']) {
                return $this->find_result_set();
            }

            return $this->_findMany();
        }

        /**
         * Tell the One that you are expecting multiple results
         * from your query, and execute it. Will return an array
         * of instances of the One class, or an empty array if
         * no rows were returned.
         * @return array
         */
        protected function _findMany()
        {
            $rows = $this->_run();

            return array_map(array($this, '_createInstanceFromRow'), $rows);
        }

        /**
         * Tell the One that you are expecting multiple results
         * from your query, and execute it. Will return a result set object
         * containing instances of the One class.
         * @return \OneResultSet
         */
        public function find_result_set()
        {
            return new OneResultSet($this->_findMany());
        }

        /**
         * Tell the One that you are expecting multiple results
         * from your query, and execute it. Will return an array,
         * or an empty array if no rows were returned.
         * @return array
         */
        public function find_array()
        {
            return $this->_run();
        }

        /**
         * Tell the One that you wish to execute a COUNT query.
         * Will return an integer representing the number of
         * rows returned.
         */
        public function count($column = '*')
        {
            return $this->_callAggregateDbFunction(__FUNCTION__, $column);
        }

        /**
         * Tell the One that you wish to execute a MAX query.
         * Will return the max value of the choosen column.
         */
        public function max($column)
        {
            return $this->_callAggregateDbFunction(__FUNCTION__, $column);
        }

        /**
         * Tell the One that you wish to execute a MIN query.
         * Will return the min value of the choosen column.
         */
        public function min($column)
        {
            return $this->_callAggregateDbFunction(__FUNCTION__, $column);
        }

        /**
         * Tell the One that you wish to execute a AVG query.
         * Will return the average value of the choosen column.
         */
        public function avg($column)
        {
            return $this->_callAggregateDbFunction(__FUNCTION__, $column);
        }

        /**
         * Tell the One that you wish to execute a SUM query.
         * Will return the sum of the choosen column.
         */
        public function sum($column)
        {
            return $this->_callAggregateDbFunction(__FUNCTION__, $column);
        }

        /**
         * Execute an aggregate query on the current connection.
         * @param string $sqlFunction The aggregate function to call eg. MIN, COUNT, etc
         * @param string $column The column to execute the aggregate query against
         * @return int
         */
        protected function _callAggregateDbFunction($sqlFunction, $column)
        {
            $alias          = Inflector::lower($sqlFunction);
            $sqlFunction    = Inflector::upper($sqlFunction);

            if('*' != $column) {
                $column = $this->_quoteIdentifier($column);
            }

            $result_columns = $this->_resultColumns;
            $this->_resultColumns = [];
            $this->select_expr("$sqlFunction($column)", $alias);
            $result = $this->find_one();
            $this->_resultColumns = $result_columns;

            $returnValue = 0;

            if ($result !== false && isset($result->$alias)) {
                if (!is_numeric($result->$alias)) {
                    $returnValue = $result->$alias;
                }
                elseif((int) $result->$alias == (float) $result->$alias) {
                    $returnValue = (int) $result->$alias;
                } else {
                    $returnValue = (float) $result->$alias;
                }
            }

            return $returnValue;
        }

         /**
         * This method can be called to hydrate (populate) this
         * instance of the class from an associative array of data.
         * This will usually be called only from inside the class,
         * but it's public in case you need to call it directly.
         */
        public function hydrate($data = [])
        {
            $this->_data = $data;

            return $this;
        }

        /**
         * Force the One to flag all the fields in the $data array
         * as "dirty" and therefore update them when save() is called.
         */
        public function force_all_dirty()
        {
            $this->_dirty_fields = $this->_data;

            return $this;
        }

        /**
         * PerfOne a raw query. The query can contain placeholders in
         * either named or question mark style. If placeholders are
         * used, the parameters should be an array of values which will
         * be bound to the placeholders in the query. If this method
         * is called, all other query building methods will be ignored.
         */
        public function raw_query($query, $parameters = [])
        {
            $this->_is_raw_query = true;
            $this->_raw_query = $query;
            $this->_raw_parameters = $parameters;

            return $this;
        }

        /**
         * Add an alias for the main table to be used in SELECT queries
         */
        public function table_alias($alias)
        {
            $this->_table_alias = $alias;

            return $this;
        }

        /**
         * Internal method to add an unquoted expression to the set
         * of columns returned by the SELECT query. The second optional
         * argument is the alias to return the expression as.
         */
        protected function _addResultColumn($expr, $alias = null)
        {
            if (!is_null($alias)) {
                $expr .= " AS " . $this->_quoteIdentifier($alias);
            }

            if ($this->_using_default_resultColumns) {
                $this->_resultColumns = array($expr);
                $this->_using_default_resultColumns = false;
            } else {
                $this->_resultColumns[] = $expr;
            }

            return $this;
        }

        /**
         * Counts the number of columns that belong to the primary
         * key and their value is null.
         */
        public function count_null_id_columns()
        {
            if (Arrays::is($this->_getIdColumnName())) {
                return count(array_filter($this->id(), 'is_null'));
            } else {
                return is_null($this->id()) ? 1 : 0;
            }
        }

        /**
         * Add a column to the list of columns returned by the SELECT
         * query. This defaults to '*'. The second optional argument is
         * the alias to return the column as.
         */
        public function select($column, $alias = null)
        {
            $column = $this->_quoteIdentifier($column);
            return $this->_addResultColumn($column, $alias);
        }

        /**
         * Add an unquoted expression to the list of columns returned
         * by the SELECT query. The second optional argument is
         * the alias to return the column as.
         */
        public function select_expr($expr, $alias = null)
        {
            return $this->_addResultColumn($expr, $alias);
        }

        /**
         * Add columns to the list of columns returned by the SELECT
         * query. This defaults to '*'. Many columns can be supplied
         * as either an array or as a list of parameters to the method.
         *
         * Note that the alias must not be numeric - if you want a
         * numeric alias then prepend it with some alpha chars. eg. a1
         *
         * @example select_many(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5');
         * @example select_many('column', 'column2', 'column3');
         * @example select_many(array('column', 'column2', 'column3'), 'column4', 'column5');
         *
         * @return \One
         */
        public function select_many()
        {
            $columns = func_get_args();

            if (!empty($columns)) {
                $columns = $this->_nOnealiseSelectManyColumns($columns);

                foreach ($columns as $alias => $column) {
                    if (is_numeric($alias)) {
                        $alias = null;
                    }

                    $this->select($column, $alias);
                }
            }

            return $this;
        }

        /**
         * Add an unquoted expression to the list of columns returned
         * by the SELECT query. Many columns can be supplied as either
         * an array or as a list of parameters to the method.
         *
         * Note that the alias must not be numeric - if you want a
         * numeric alias then prepend it with some alpha chars. eg. a1
         *
         * @example select_many_expr(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5')
         * @example select_many_expr('column', 'column2', 'column3')
         * @example select_many_expr(array('column', 'column2', 'column3'), 'column4', 'column5')
         *
         * @return \One
         */
        public function select_many_expr()
        {
            $columns = func_get_args();

            if (!empty($columns)) {
                $columns = $this->_nOnealiseSelectManyColumns($columns);

                foreach ($columns as $alias => $column) {
                    if (is_numeric($alias)) {
                        $alias = null;
                    }

                    $this->select_expr($column, $alias);
                }
            }

            return $this;
        }

        /**
         * Take a column specification for the select many methods and convert it
         * into a nOnealised array of columns and aliases.
         *
         * It is designed to turn the following styles into a nOnealised array:
         *
         * array(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5'))
         *
         * @param array $columns
         * @return array
         */
        protected function _nOnealiseSelectManyColumns($columns)
        {
            $return = [];

            foreach($columns as $column) {
                if(Arrays::is($column)) {
                    foreach($column as $key => $value) {
                        if(!is_numeric($key)) {
                            $return[$key] = $value;
                        } else {
                            $return[] = $value;
                        }
                    }
                } else {
                    $return[] = $column;
                }
            }

            return $return;
        }

        /**
         * Add a DISTINCT keyword before the list of columns in the SELECT query
         */
        public function distinct()
        {
            $this->_distinct = true;

            return $this;
        }

        /**
         * Internal method to add a JOIN source to the query.
         *
         * The join_operator should be one of INNER, LEFT OUTER, CROSS etc - this
         * will be prepended to JOIN.
         *
         * The table should be the name of the table to join to.
         *
         * The constraint may be either a string or an array with three elements. If it
         * is a string, it will be compiled into the query as-is, with no escaping. The
         * recommended way to supply the constraint is as an array with three elements:
         *
         * first_column, operator, second_column
         *
         * Example: array('user.id', '=', 'profile.user_id')
         *
         * will compile to
         *
         * ON `user`.`id` = `profile`.`user_id`
         *
         * The final (optional) argument specifies an alias for the joined table.
         */
        protected function _addJoinSource($join_operator, $table, $constraint, $table_alias = null) {

            $join_operator = trim("{$join_operator} JOIN");

            $table = $this->_quoteIdentifier($table);

            // Add table alias if present
            if (!is_null($table_alias)) {
                $table_alias = $this->_quoteIdentifier($table_alias);
                $table .= " {$table_alias}";
            }

            // Build the constraint
            if (Arrays::is($constraint)) {
                list($first_column, $operator, $second_column) = $constraint;

                $first_column = $this->_quoteIdentifier($first_column);
                $second_column = $this->_quoteIdentifier($second_column);
                $constraint = "{$first_column} {$operator} {$second_column}";
            }

            $this->_join_sources[] = "{$join_operator} {$table} ON {$constraint}";

            return $this;
        }

        /**
         * Add a RAW JOIN source to the query
         */
        public function raw_join($table, $constraint, $table_alias, $parameters = [])
        {
            // Add table alias if present
            if (!is_null($table_alias)) {
                $table_alias = $this->_quoteIdentifier($table_alias);
                $table .= " {$table_alias}";
            }

            $this->_values = array_merge($this->_values, $parameters);

            // Build the constraint
            if (Arrays::is($constraint)) {
                list($first_column, $operator, $second_column) = $constraint;

                $first_column = $this->_quoteIdentifier($first_column);
                $second_column = $this->_quoteIdentifier($second_column);
                $constraint = "{$first_column} {$operator} {$second_column}";
            }

            $this->_join_sources[] = "{$table} ON {$constraint}";

            return $this;
        }

        /**
         * Add a simple JOIN source to the query
         */
        public function join($table, $constraint, $table_alias = null)
        {
            return $this->_addJoinSource("", $table, $constraint, $table_alias);
        }

        /**
         * Add an INNER JOIN souce to the query
         */
        public function inner_join($table, $constraint, $table_alias = null)
        {
            return $this->_addJoinSource("INNER", $table, $constraint, $table_alias);
        }

        /**
         * Add a LEFT OUTER JOIN souce to the query
         */
        public function left_outer_join($table, $constraint, $table_alias = null)
        {
            return $this->_addJoinSource("LEFT OUTER", $table, $constraint, $table_alias);
        }

        /**
         * Add an RIGHT OUTER JOIN souce to the query
         */
        public function right_outer_join($table, $constraint, $table_alias = null)
        {
            return $this->_addJoinSource("RIGHT OUTER", $table, $constraint, $table_alias);
        }

        /**
         * Add an FULL OUTER JOIN souce to the query
         */
        public function full_outer_join($table, $constraint, $table_alias = null)
        {
            return $this->_addJoinSource("FULL OUTER", $table, $constraint, $table_alias);
        }

        /**
         * Internal method to add a HAVING condition to the query
         */
        protected function _addHaving($fragment, $values = [])
        {
            return $this->_addCondition('having', $fragment, $values);
        }

        /**
         * Internal method to add a HAVING condition to the query
         */
        protected function _add_simple_having($column_name, $separator, $value)
        {
            return $this->_addSimpleCondition('having', $column_name, $separator, $value);
        }

        /**
         * Internal method to add a HAVING clause with multiple values (like IN and NOT IN)
         */
        public function _addHavingPlaceholder($column_name, $separator, $values)
        {
            if (!Arrays::is($column_name)) {
                $data = array($column_name => $values);
            } else {
                $data = $column_name;
            }

            $result = $this;

            foreach ($data as $key => $val) {
                $column = $result->_quoteIdentifier($key);
                $placeholders = $result->_createPlaceholders($val);
                $result = $result->_addHaving("{$column} {$separator} ({$placeholders})", $val);
            }

            return $result;
        }

        /**
         * Internal method to add a HAVING clause with no parameters(like IS NULL and IS NOT NULL)
         */
        public function _addHavingNoValue($column_name, $operator)
        {
            $conditions = (Arrays::is($column_name)) ? $column_name : array($column_name);
            $result = $this;

            foreach($conditions as $column) {
                $column = $this->_quoteIdentifier($column);
                $result = $result->_addHaving("{$column} {$operator}");
            }

            return $result;
        }

        /**
         * Internal method to add a WHERE condition to the query
         */
        protected function _addWhere($fragment, $values = [])
        {
            return $this->_addCondition('where', $fragment, $values);
        }

        /**
         * Internal method to add a WHERE condition to the query
         */
        protected function _addSimpleWhere($column_name, $separator, $value)
        {
            return $this->_addSimpleCondition('where', $column_name, $separator, $value);
        }

        /**
         * Add a WHERE clause with multiple values (like IN and NOT IN)
         */
        public function _addWherePlaceholder($column_name, $separator, $values)
        {
            if (!Arrays::is($column_name)) {
                $data = array($column_name => $values);
            } else {
                $data = $column_name;
            }

            $result = $this;

            foreach ($data as $key => $val) {
                $column = $result->_quoteIdentifier($key);
                $placeholders = $result->_createPlaceholders($val);
                $result = $result->_addWhere("{$column} {$separator} ({$placeholders})", $val);
            }

            return $result;
        }

        /**
         * Add a WHERE clause with no parameters(like IS NULL and IS NOT NULL)
         */
        public function _addWhereNoValue($column_name, $operator)
        {
            $conditions = (Arrays::is($column_name)) ? $column_name : array($column_name);
            $result = $this;

            foreach($conditions as $column) {
                $column = $this->_quoteIdentifier($column);
                $result = $result->_addWhere("{$column} {$operator}");
            }

            return $result;
        }

        /**
         * Internal method to add a HAVING or WHERE condition to the query
         */
        protected function _addCondition($type, $fragment, $values = [])
        {
            $conditions_class_property_name = "_{$type}_conditions";

            if (!Arrays::is($values)) {
                $values = array($values);
            }

            array_push(
                $this->$conditions_class_property_name,
                array(
                    self::CONDITION_FRAGMENT => $fragment,
                    self::CONDITION_VALUES => $values,
                )
            );

            return $this;
        }

       /**
         * Helper method to compile a simple COLUMN SEPARATOR VALUE
         * style HAVING or WHERE condition into a string and value ready to
         * be passed to the _addCondition method. Avoids duplication
         * of the call to _quoteIdentifier
         *
         * If column_name is an associative array, it will add a condition for each column
         */
        protected function _addSimpleCondition($type, $column_name, $separator, $value)
        {
            $multiple = Arrays::is($column_name) ? $column_name : array($column_name => $value);
            $result = $this;

            foreach($multiple as $key => $val) {
                // Add the table name in case of ambiguous columns
                if (count($result->_join_sources) > 0 && strpos($key, '.') === false) {
                    $table = $result->_table_name;

                    if (!is_null($result->_table_alias)) {
                        $table = $result->_table_alias;
                    }

                    $key = "{$table}.{$key}";
                }

                $key = $result->_quoteIdentifier($key);
                $result = $result->_addCondition($type, "{$key} {$separator} ?", $val);
            }

            return $result;
        }

        /**
         * Return a string containing the given number of question marks,
         * separated by commas. Eg "?, ?, ?"
         */
        protected function _createPlaceholders($fields)
        {
            if (!empty($fields)) {
                $db_fields = [];

                foreach($fields as $key => $value) {
                    // Process expression fields directly into the query
                    if(Arrays::exists($key, $this->_expr_fields)) {
                        $db_fields[] = $value;
                    } else {
                        $db_fields[] = '?';
                    }
                }

                return implode(', ', $db_fields);
            }
        }

        /**
         * Helper method that filters a column/value array returning only those
         * columns that belong to a compound primary key.
         *
         * If the key contains a column that does not exist in the given array,
         * a null value will be returned for it.
         */
        protected function _getCompoundIdColumnValues($value)
        {
            $filtered = [];

            foreach($this->_getIdColumnName() as $key) {
                $filtered[$key] = isset($value[$key]) ? $value[$key] : null;
            }

            return $filtered;
        }

       /**
         * Helper method that filters an array containing compound column/value
         * arrays.
         */
        protected function _getCompoundIdColumnValuesArray($values)
        {
            $filtered = [];

            foreach($values as $value) {
                $filtered[] = $this->_getCompoundIdColumnValues($value);
            }

            return $filtered;
        }

        /**
         * Add a WHERE column = value clause to your query. Each time
         * this is called in the chain, an additional WHERE will be
         * added, and these will be ANDed together when the final query
         * is built.
         *
         * If you use an array in $column_name, a new clause will be
         * added for each element. In this case, $value is ignored.
         */
        public function where($column_name, $value = null)
        {
            return $this->where_equal($column_name, $value);
        }

        /**
         * More explicitly named version of for the where() method.
         * Can be used if preferred.
         */
        public function where_equal($column_name, $value = null)
        {
            return $this->_addSimpleWhere($column_name, '=', $value);
        }

        /**
         * Add a WHERE column != value clause to your query.
         */
        public function where_not_equal($column_name, $value = null)
        {
            return $this->_addSimpleWhere($column_name, '!=', $value);
        }

        /**
         * Special method to query the table by its primary key
         *
         * If primary key is compound, only the columns that
         * belong to they key will be used for the query
         */
        public function where_id_is($id)
        {
            return (Arrays::is($this->_getIdColumnName())) ?
                $this->where($this->_getCompoundIdColumnValues($id), null) :
                $this->where($this->_getIdColumnName(), $id);
        }

        /**
         * Allows adding a WHERE clause that matches any of the conditions
         * specified in the array. Each element in the associative array will
         * be a different condition, where the key will be the column name.
         *
         * By default, an equal operator will be used against all columns, but
         * it can be overriden for any or every column using the second parameter.
         *
         * Each condition will be ORed together when added to the final query.
         */
        public function where_any_is($values, $operator = '=')
        {
            $data = [];
            $query = array("((");
            $first = true;

            foreach ($values as $item) {
                if ($first) {
                    $first = false;
                } else {
                    $query[] = ") OR (";
                }

                $firstsub = true;

                foreach($item as $key => $item) {
                    $op = is_string($operator) ? $operator : (isset($operator[$key]) ? $operator[$key] : '=');

                    if ($firstsub) {
                        $firstsub = false;
                    } else {
                        $query[] = "AND";
                    }

                    $query[] = $this->_quoteIdentifier($key);
                    $data[] = $item;
                    $query[] = $op . " ?";
                }
            }

            $query[] = "))";

            return $this->where_raw(join($query, ' '), $data);
        }

        /**
         * Similar to where_id_is() but allowing multiple primary keys.
         *
         * If primary key is compound, only the columns that
         * belong to they key will be used for the query
         */
        public function where_id_in($ids)
        {
            return (Arrays::is($this->_getIdColumnName())) ?
                $this->where_any_is($this->_getCompoundIdColumnValuesArray($ids)) :
                $this->where_in($this->_getIdColumnName(), $ids);
        }

        /**
         * Add a WHERE ... LIKE clause to your query.
         */
        public function where_like($column_name, $value = null)
        {
            return $this->_addSimpleWhere($column_name, 'LIKE', $value);
        }

        /**
         * Add where WHERE ... NOT LIKE clause to your query.
         */
        public function where_not_like($column_name, $value = null)
        {
            return $this->_addSimpleWhere($column_name, 'NOT LIKE', $value);
        }

        /**
         * Add a WHERE ... > clause to your query
         */
        public function where_gt($column_name, $value = null)
        {
            return $this->_addSimpleWhere($column_name, '>', $value);
        }

        /**
         * Add a WHERE ... < clause to your query
         */
        public function where_lt($column_name, $value = null)
        {
            return $this->_addSimpleWhere($column_name, '<', $value);
        }

        /**
         * Add a WHERE ... >= clause to your query
         */
        public function where_gte($column_name, $value = null)
        {
            return $this->_addSimpleWhere($column_name, '>=', $value);
        }

        /**
         * Add a WHERE ... <= clause to your query
         */
        public function where_lte($column_name, $value = null)
        {
            return $this->_addSimpleWhere($column_name, '<=', $value);
        }

        /**
         * Add a WHERE ... IN clause to your query
         */
        public function where_in($column_name, $values)
        {
            return $this->_addWherePlaceholder($column_name, 'IN', $values);
        }

        /**
         * Add a WHERE ... NOT IN clause to your query
         */
        public function where_not_in($column_name, $values)
        {
            return $this->_addWherePlaceholder($column_name, 'NOT IN', $values);
        }

        /**
         * Add a WHERE column IS NULL clause to your query
         */
        public function where_null($column_name)
        {
            return $this->_addWhereNoValue($column_name, "IS NULL");
        }

        /**
         * Add a WHERE column IS NOT NULL clause to your query
         */
        public function where_not_null($column_name)
        {
            return $this->_addWhereNoValue($column_name, "IS NOT NULL");
        }

        /**
         * Add a raw WHERE clause to the query. The clause should
         * contain question mark placeholders, which will be bound
         * to the parameters supplied in the second argument.
         */
        public function where_raw($clause, $parameters = [])
        {
            return $this->_addWhere($clause, $parameters);
        }

        /**
         * Add a LIMIT to the query
         */
        public function limit($limit)
        {
            $this->_limit = $limit;

            return $this;
        }

        /**
         * Add an OFFSET to the query
         */
        public function offset($offset)
        {
            $this->_offset = $offset;

            return $this;
        }

        /**
         * Add an ORDER BY clause to the query
         */
        protected function _addOrderBy($column_name, $ordering)
        {
            $column_name = $this->_quoteIdentifier($column_name);
            $this->_order_by[] = "{$column_name} {$ordering}";

            return $this;
        }

        /**
         * Add an ORDER BY column DESC clause
         */
        public function order_by_desc($column_name)
        {
            return $this->_addOrderBy($column_name, 'DESC');
        }

        /**
         * Add an ORDER BY column ASC clause
         */
        public function order_by_asc($column_name)
        {
            return $this->_addOrderBy($column_name, 'ASC');
        }

        /**
         * Add an unquoted expression as an ORDER BY clause
         */
        public function order_by_expr($clause)
        {
            $this->_order_by[] = $clause;

            return $this;
        }

        /**
         * Add a column to the list of columns to GROUP BY
         */
        public function group_by($column_name)
        {
            $column_name = $this->_quoteIdentifier($column_name);
            $this->_group_by[] = $column_name;

            return $this;
        }

        /**
         * Add an unquoted expression to the list of columns to GROUP BY
         */
        public function group_by_expr($expr)
        {
            $this->_group_by[] = $expr;

            return $this;
        }

        /**
         * Add a HAVING column = value clause to your query. Each time
         * this is called in the chain, an additional HAVING will be
         * added, and these will be ANDed together when the final query
         * is built.
         *
         * If you use an array in $column_name, a new clause will be
         * added for each element. In this case, $value is ignored.
         */
        public function having($column_name, $value = null)
        {
            return $this->having_equal($column_name, $value);
        }

        /**
         * More explicitly named version of for the having() method.
         * Can be used if preferred.
         */
        public function having_equal($column_name, $value = null)
        {
            return $this->_add_simple_having($column_name, '=', $value);
        }

        /**
         * Add a HAVING column != value clause to your query.
         */
        public function having_not_equal($column_name, $value = null)
        {
            return $this->_add_simple_having($column_name, '!=', $value);
        }

        /**
         * Special method to query the table by its primary key.
         *
         * If primary key is compound, only the columns that
         * belong to they key will be used for the query
         */
        public function having_id_is($id)
        {
            return (Arrays::is($this->_getIdColumnName())) ?
                $this->having($this->_getCompoundIdColumnValues($value)) :
                $this->having($this->_getIdColumnName(), $id);
        }

        /**
         * Add a HAVING ... LIKE clause to your query.
         */
        public function having_like($column_name, $value = null)
        {
            return $this->_add_simple_having($column_name, 'LIKE', $value);
        }

        /**
         * Add where HAVING ... NOT LIKE clause to your query.
         */
        public function having_not_like($column_name, $value = null)
        {
            return $this->_add_simple_having($column_name, 'NOT LIKE', $value);
        }

        /**
         * Add a HAVING ... > clause to your query
         */
        public function having_gt($column_name, $value = null)
        {
            return $this->_add_simple_having($column_name, '>', $value);
        }

        /**
         * Add a HAVING ... < clause to your query
         */
        public function having_lt($column_name, $value = null)
        {
            return $this->_add_simple_having($column_name, '<', $value);
        }

        /**
         * Add a HAVING ... >= clause to your query
         */
        public function having_gte($column_name, $value = null)
        {
            return $this->_add_simple_having($column_name, '>=', $value);
        }

        /**
         * Add a HAVING ... <= clause to your query
         */
        public function having_lte($column_name, $value = null)
        {
            return $this->_add_simple_having($column_name, '<=', $value);
        }

        /**
         * Add a HAVING ... IN clause to your query
         */
        public function having_in($column_name, $values = null)
        {
            return $this->_addHavingPlaceholder($column_name, 'IN', $values);
        }

        /**
         * Add a HAVING ... NOT IN clause to your query
         */
        public function having_not_in($column_name, $values = null)
        {
            return $this->_addHavingPlaceholder($column_name, 'NOT IN', $values);
        }

        /**
         * Add a HAVING column IS NULL clause to your query
         */
        public function having_null($column_name)
        {
            return $this->_addHavingNoValue($column_name, 'IS NULL');
        }

        /**
         * Add a HAVING column IS NOT NULL clause to your query
         */
        public function having_not_null($column_name)
        {
            return $this->_addHavingNoValue($column_name, 'IS NOT NULL');
        }

        /**
         * Add a raw HAVING clause to the query. The clause should
         * contain question mark placeholders, which will be bound
         * to the parameters supplied in the second argument.
         */
        public function having_raw($clause, $parameters = [])
        {
            return $this->_addHaving($clause, $parameters);
        }

        /**
         * Build a SELECT statement based on the clauses that have
         * been passed to this instance by chaining method calls.
         */
        protected function _buildSelect()
        {
            // If the query is raw, just set the $this->_values to be
            // the raw query parameters and return the raw query
            if ($this->_is_raw_query) {
                $this->_values = $this->_raw_parameters;
                return $this->_raw_query;
            }

            // Build and return the full SELECT statement by concatenating
            // the results of calling each separate builder method.
            return $this->_joinIfNotEmpty(
                " ",
                array(
                    $this->_buildSelectStart(),
                    $this->_buildJoin(),
                    $this->_buildWhere(),
                    $this->_buildGroupBy(),
                    $this->_buildHaving(),
                    $this->_buildOrderBy(),
                    $this->_buildLimit(),
                    $this->_buildOffset(),
                )
            );
        }

        /**
         * Build the start of the SELECT statement
         */
        protected function _buildSelectStart()
        {
            $fragment = 'SELECT ';
            $result_columns = join(', ', $this->_resultColumns);

            if (!is_null($this->_limit) &&
                self::$_config[$this->_connection_name]['limit_clause_style'] === One::LIMIT_STYLE_TOP_N) {
                $fragment .= "TOP {$this->_limit} ";
            }

            if ($this->_distinct) {
                $result_columns = 'DISTINCT ' . $result_columns;
            }

            $fragment .= "{$result_columns} FROM " . $this->_quoteIdentifier($this->_table_name);

            if (!is_null($this->_table_alias)) {
                $fragment .= " " . $this->_quoteIdentifier($this->_table_alias);
            }

            return $fragment;
        }

        /**
         * Build the JOIN sources
         */
        protected function _buildJoin()
        {
            if (count($this->_join_sources) === 0) {
                return '';
            }

            return join(" ", $this->_join_sources);
        }

        /**
         * Build the WHERE clause(s)
         */
        protected function _buildWhere()
        {
            return $this->_buildConditions('where');
        }

        /**
         * Build the HAVING clause(s)
         */
        protected function _buildHaving()
        {
            return $this->_buildConditions('having');
        }

        /**
         * Build GROUP BY
         */
        protected function _buildGroupBy()
        {
            if (count($this->_group_by) === 0) {
                return '';
            }

            return "GROUP BY " . join(", ", $this->_group_by);
        }

        /**
         * Build a WHERE or HAVING clause
         * @param string $type
         * @return string
         */
        protected function _buildConditions($type)
        {
            $conditions_class_property_name = "_{$type}_conditions";
            // If there are no clauses, return empty string
            if (count($this->$conditions_class_property_name) === 0) {
                return '';
            }

            $conditions = [];

            foreach ($this->$conditions_class_property_name as $condition) {
                $conditions[] = $condition[self::CONDITION_FRAGMENT];
                $this->_values = array_merge($this->_values, $condition[self::CONDITION_VALUES]);
            }

            return Inflector::upper($type) . " " . join(" AND ", $conditions);
        }

        /**
         * Build ORDER BY
         */
        protected function _buildOrderBy()
        {
            if (count($this->_order_by) === 0) {
                return '';
            }

            return "ORDER BY " . join(", ", $this->_order_by);
        }

        /**
         * Build LIMIT
         */
        protected function _buildLimit()
        {
            $fragment = '';
            if (!is_null($this->_limit) &&
                self::$_config[$this->_connection_name]['limit_clause_style'] == One::LIMIT_STYLE_LIMIT) {

                if (self::get_db($this->_connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME) == 'firebird') {
                    $fragment = 'ROWS';
                } else {
                    $fragment = 'LIMIT';
                }

                $fragment .= " {$this->_limit}";
            }

            return $fragment;
        }

        /**
         * Build OFFSET
         */
        protected function _buildOffset()
        {
            if (!is_null($this->_offset)) {
                $clause = 'OFFSET';

                if (self::get_db($this->_connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME) == 'firebird') {
                    $clause = 'TO';
                }

                return "$clause " . $this->_offset;
            }

            return '';
        }

        /**
         * Wrapper around PHP's join function which
         * only adds the pieces if they are not empty.
         */
        protected function _joinIfNotEmpty($glue, $pieces)
        {
            $filtered_pieces = [];

            foreach ($pieces as $piece) {
                if (is_string($piece)) {
                    $piece = trim($piece);
                }

                if (!empty($piece)) {
                    $filtered_pieces[] = $piece;
                }
            }

            return join($glue, $filtered_pieces);
        }

        /**
         * Quote a string that is used as an identifier
         * (table names, column names etc). This method can
         * also deal with dot-separated identifiers eg table.column
         */
        protected function _quoteOneIdentifier($identifier)
        {
            $parts = explode('.', $identifier);
            $parts = array_map(array($this, '_quoteIdentifierPart'), $parts);

            return join('.', $parts);
        }

        /**
         * Quote a string that is used as an identifier
         * (table names, column names etc) or an array containing
         * multiple identifiers. This method can also deal with
         * dot-separated identifiers eg table.column
         */
        protected function _quoteIdentifier($identifier)
        {
            if (Arrays::is($identifier)) {
                $result = array_map(array($this, '_quoteOneIdentifier'), $identifier);

                return join(', ', $result);
            } else {
                return $this->_quoteOneIdentifier($identifier);
            }
        }

        /**
         * This method perfOnes the actual quoting of a single
         * part of an identifier, using the identifier quote
         * character specified in the config (or autodetected).
         */
        protected function _quoteIdentifierPart($part)
        {
            if ($part === '*') {
                return $part;
            }

            $quote_character = self::$_config[$this->_connection_name]['identifier_quote_character'];
            // double up any identifier quotes to escape them
            return $quote_character .
                str_replace($quote_character,
                    $quote_character . $quote_character,
                    $part
            ) . $quote_character;
        }

        /**
         * Create a cache key for the given query and parameters.
         */
        protected static function _createCacheKey($query, $parameters, $table_name = null, $connection_name = self::DEFAULT_CONNECTION)
        {
            if(isset(self::$_config[$connection_name]['create_cache_key']) && is_callable(self::$_config[$connection_name]['create_cache_key'])) {
                return call_user_func_array(self::$_config[$connection_name]['create_cache_key'], array($query, $parameters, $table_name, $connection_name));
            }

            $parameter_string = join(',', $parameters);
            $key = $query . ':' . $parameter_string;

            return sha1($key);
        }

        /**
         * Check the query cache for the given cache key. If a value
         * is cached for the key, return the value. Otherwise, return false.
         */
        protected static function _checkQueryCache($cache_key, $table_name = null, $connection_name = self::DEFAULT_CONNECTION)
        {
            if(isset(self::$_config[$connection_name]['check_query_cache']) && is_callable(self::$_config[$connection_name]['check_query_cache'])){
                return call_user_func_array(self::$_config[$connection_name]['check_query_cache'], array($cache_key, $table_name, $connection_name));
            } elseif (isset(self::$_query_cache[$connection_name][$cache_key])) {
                return self::$_query_cache[$connection_name][$cache_key];
            }

            return false;
        }

        /**
         * Clear the query cache
         */
        public static function clear_cache($table_name = null, $connection_name = self::DEFAULT_CONNECTION)
        {
            self::$_query_cache = [];

            if(isset(self::$_config[$connection_name]['clear_cache']) && is_callable(self::$_config[$connection_name]['clear_cache'])){
                return call_user_func_array(self::$_config[$connection_name]['clear_cache'], array($table_name, $connection_name));
            }
        }

        /**
         * Add the given value to the query cache.
         */
        protected static function _cacheQueryResult($cache_key, $value, $table_name = null, $connection_name = self::DEFAULT_CONNECTION)
        {
            if(isset(self::$_config[$connection_name]['cache_query_result']) && is_callable(self::$_config[$connection_name]['cache_query_result'])){
                return call_user_func_array(self::$_config[$connection_name]['cache_query_result'], array($cache_key, $value, $table_name, $connection_name));
            } elseif (!isset(self::$_query_cache[$connection_name])) {
                self::$_query_cache[$connection_name] = [];
            }

            self::$_query_cache[$connection_name][$cache_key] = $value;
        }

        /**
         * Execute the SELECT query that has been built up by chaining methods
         * on this class. Return an array of rows as associative arrays.
         */
        protected function _run()
        {
            $query = $this->_buildSelect();
            $caching_enabled = self::$_config[$this->_connection_name]['caching'];

            if ($caching_enabled) {
                $cache_key = self::_createCacheKey($query, $this->_values, $this->_table_name, $this->_connection_name);
                $cached_result = self::_checkQueryCache($cache_key, $this->_table_name, $this->_connection_name);

                if ($cached_result !== false) {
                    return $cached_result;
                }
            }

            self::_execute($query, $this->_values, $this->_connection_name);
            $statement = self::get_last_statement();

            $rows = [];

            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = $row;
            }

            if ($caching_enabled) {
                self::_cacheQueryResult($cache_key, $rows, $this->_table_name, $this->_connection_name);
            }

            // reset One after executing the query
            $this->_values = [];
            $this->_resultColumns = array('*');
            $this->_using_default_resultColumns = true;

            return $rows;
        }

        /**
         * Return the raw data wrapped by this One
         * instance as an associative array. Column
         * names may optionally be supplied as arguments,
         * if so, only those keys will be returned.
         */
        public function as_array()
        {
            if (func_num_args() === 0) {
                return $this->_data;
            }

            $args = func_get_args();

            return array_intersect_key(
                $this->_data,
                array_flip($args)
            );
        }

        public function to_array()
        {
            return $this->as_array();
        }

        /**
         * Return the value of a property of this object (database row)
         * or null if not present.
         *
         * If a column-names array is passed, it will return a associative array
         * with the value of each column or null if it is not present.
         */
        public function get($key)
        {
            if (Arrays::is($key)) {
                $result = [];

                foreach($key as $column) {
                    $result[$column] = isset($this->_data[$column]) ? $this->_data[$column] : null;
                }

                return $result;
            } else {
                return isset($this->_data[$key]) ? $this->_data[$key] : null;
            }
        }

        /**
         * Return the name of the column in the database table which contains
         * the primary key ID of the row.
         */
        protected function _getIdColumnName()
        {
            if (!is_null($this->_instance_id_column)) {
                return $this->_instance_id_column;
            }

            if (isset(self::$_config[$this->_connection_name]['id_column_overrides'][$this->_table_name])) {
                return self::$_config[$this->_connection_name]['id_column_overrides'][$this->_table_name];
            }

            return self::$_config[$this->_connection_name]['id_column'];
        }

        /**
         * Get the primary key ID of this object.
         */
        public function id($disallowNull = false)
        {
            $id = $this->get($this->_getIdColumnName());

            if ($disallowNull) {
                if (Arrays::is($id)) {
                    foreach ($id as $id_part) {
                        if ($id_part === null) {
                            throw new Exception('Primary key ID contains null value(s)');
                        }
                    }
                } else if ($id === null) {
                    throw new Exception('Primary key ID missing from row or is null');
                }
            }

            return $id;
        }

        /**
         * Set a property to a particular value on this object.
         * To set multiple properties at once, pass an associative array
         * as the first parameter and leave out the second parameter.
         * Flags the properties as 'dirty' so they will be saved to the
         * database when save() is called.
         */
        public function set($key, $value = null)
        {
            return $this->_setOneProperty($key, $value);
        }

        /**
         * Set a property to a particular value on this object.
         * To set multiple properties at once, pass an associative array
         * as the first parameter and leave out the second parameter.
         * Flags the properties as 'dirty' so they will be saved to the
         * database when save() is called.
         * @param string|array $key
         * @param string|null $value
         */
        public function set_expr($key, $value = null)
        {
            return $this->_setOneProperty($key, $value, true);
        }

        /**
         * Set a property on the One object.
         * @param string|array $key
         * @param string|null $value
         * @param bool $raw Whether this value should be treated as raw or not
         */
        protected function _setOneProperty($key, $value = null, $expr = false)
        {
            if (!Arrays::is($key)) {
                $key = array($key => $value);
            }

            foreach ($key as $field => $value) {
                $this->_data[$field] = $value;
                $this->_dirty_fields[$field] = $value;

                if (false === $expr and isset($this->_expr_fields[$field])) {
                    unset($this->_expr_fields[$field]);
                } else if (true === $expr) {
                    $this->_expr_fields[$field] = true;
                }
            }

            return $this;
        }

        /**
         * Check whether the given field has been changed since this
         * object was saved.
         */
        public function is_dirty($key)
        {
            return isset($this->_dirty_fields[$key]);
        }

        /**
         * Check whether the model was the result of a call to create() or not
         * @return bool
         */
        public function is_new()
        {
            return $this->_is_new;
        }

        /**
         * Save any fields which have been modified on this object
         * to the database.
         */
        public function save()
        {
            $query = [];

            // remove any expression fields as they are already baked into the query
            $values = array_values(array_diff_key($this->_dirty_fields, $this->_expr_fields));

            if (!$this->_is_new) { // UPDATE
                // If there are no dirty values, do nothing
                if (empty($values) && empty($this->_expr_fields)) {
                    return true;
                }

                $query = $this->_buildUpdate();
                $id = $this->id(true);

                if (Arrays::is($id)) {
                    $values = array_merge($values, array_values($id));
                } else {
                    $values[] = $id;
                }
            } else { // INSERT
                $query = $this->_buildInsert();
            }

            $success = self::_execute($query, $values, $this->_connection_name);
            $caching_auto_clear_enabled = self::$_config[$this->_connection_name]['caching_auto_clear'];

            if ($caching_auto_clear_enabled){
                self::clear_cache($this->_table_name, $this->_connection_name);
            }

            // If we've just inserted a new record, set the ID of this object
            if ($this->_is_new) {
                $this->_is_new = false;

                if ($this->count_null_id_columns() != 0) {
                    $db = self::get_db($this->_connection_name);

                    if($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
                        // it may return several columns if a compound primary
                        // key is used
                        $row = self::get_last_statement()->fetch(PDO::FETCH_ASSOC);

                        foreach($row as $key => $value) {
                            $this->_data[$key] = $value;
                        }
                    } else {
                        $column = $this->_getIdColumnName();
                        // if the primary key is compound, assign the last inserted id
                        // to the first column

                        if (Arrays::is($column)) {
                            $column = array_slice($column, 0, 1);
                        }

                        $this->_data[$column] = $db->lastInsertId();
                    }
                }
            }

            $this->_dirty_fields = $this->_expr_fields = [];

            return $success;
        }

        /**
         * Add a WHERE clause for every column that belongs to the primary key
         */
        public function _addIdColumnConditions(&$query)
        {
            $query[] = "WHERE";
            $keys = Arrays::is($this->_getIdColumnName()) ? $this->_getIdColumnName() : array( $this->_getIdColumnName() );
            $first = true;

            foreach($keys as $key) {
                if ($first) {
                    $first = false;
                } else {
                    $query[] = "AND";
                }

                $query[] = $this->_quoteIdentifier($key);
                $query[] = "= ?";
            }
        }

        /**
         * Build an UPDATE query
         */
        protected function _buildUpdate()
        {
            $query = [];
            $query[] = "UPDATE {$this->_quoteIdentifier($this->_table_name)} SET";

            $field_list = [];

            foreach ($this->_dirty_fields as $key => $value) {
                if(!Arrays::exists($key, $this->_expr_fields)) {
                    $value = '?';
                }

                $field_list[] = "{$this->_quoteIdentifier($key)} = $value";
            }

            $query[] = join(", ", $field_list);
            $this->_addIdColumnConditions($query);

            return join(" ", $query);
        }

        /**
         * Build an INSERT query
         */
        protected function _buildInsert()
        {
            $query[] = "INSERT INTO";
            $query[] = $this->_quoteIdentifier($this->_table_name);
            $field_list = array_map(array($this, '_quoteIdentifier'), array_keys($this->_dirty_fields));
            $query[] = "(" . join(", ", $field_list) . ")";
            $query[] = "VALUES";

            $placeholders = $this->_createPlaceholders($this->_dirty_fields);
            $query[] = "({$placeholders})";

            if (self::get_db($this->_connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
                $query[] = 'RETURNING ' . $this->_quoteIdentifier($this->_getIdColumnName());
            }

            return join(" ", $query);
        }

        /**
         * Delete this record from the database
         */
        public function delete()
        {
            $query = array(
                "DELETE FROM",
                $this->_quoteIdentifier($this->_table_name)
            );

            $this->_addIdColumnConditions($query);

            return self::_execute(join(" ", $query), Arrays::is($this->id(true)) ? array_values($this->id(true)) : array($this->id(true)), $this->_connection_name);
        }

        /**
         * Delete many records from the database
         */
        public function delete_many()
        {
            // Build and return the full DELETE statement by concatenating
            // the results of calling each separate builder method.
            $query = $this->_joinIfNotEmpty(" ", array(
                "DELETE FROM",
                $this->_quoteIdentifier($this->_table_name),
                $this->_buildWhere(),
            ));

            return self::_execute($query, $this->_values, $this->_connection_name);
        }

        // --------------------- //
        // ---  ArrayAccess  --- //
        // --------------------- //

        public function offsetExists($key)
        {
            return Arrays::exists($key, $this->_data);
        }

        public function offsetGet($key)
        {
            return $this->get($key);
        }

        public function offsetSet($key, $value)
        {
            if(is_null($key)) {
                throw new InvalidArgumentException('You must specify a key/array index.');
            }

            $this->set($key, $value);
        }

        public function offsetUnset($key)
        {
            unset($this->_data[$key]);
            unset($this->_dirty_fields[$key]);
        }

        // --------------------- //
        // --- MAGIC METHODS --- //
        // --------------------- //
        public function __get($key)
        {
            return $this->offsetGet($key);
        }

        public function __set($key, $value)
        {
            $this->offsetSet($key, $value);
        }

        public function __unset($key)
        {
            $this->offsetUnset($key);
        }


        public function __isset($key)
        {
            return $this->offsetExists($key);
        }

        /**
         * Magic method to capture calls to undefined class methods.
         * In this case we are attempting to convert camel case fOneatted
         * methods into underscore fOneatted methods.
         *
         * This allows us to call One methods using camel case and remain
         * backwards compatible.
         *
         * @param  string   $name
         * @param  array    $arguments
         * @return One
         */
        public function __call($name, $arguments)
        {
            $method = Inflector::lower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));

            if (method_exists($this, $method)) {
                return call_user_func_array([$this, $method], $arguments);
            } else {
                throw new OneMethodMissingException("Method $name() does not exist in class " . get_class($this));
            }
        }

        /**
         * Magic method to capture calls to undefined static class methods.
         * In this case we are attempting to convert camel case fOneatted
         * methods into underscore fOneatted methods.
         *
         * This allows us to call One methods using camel case and remain
         * backwards compatible.
         *
         * @param  string   $name
         * @param  array    $arguments
         * @return One
         */
        public static function __callStatic($name, $arguments)
        {
            $method = Inflector::lower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));

            return call_user_func_array(['One', $method], $arguments);
        }
    }

    /**
     * A class to handle str_replace operations that involve quoted strings
     * @example OneString::str_replace_outside_quotes('?', '%s', 'columnA = "Hello?" AND columnB = ?');
     * @example OneString::value('columnA = "Hello?" AND columnB = ?')->replace_outside_quotes('?', '%s');
     */
    class OneString
    {
        protected $subject;
        protected $search;
        protected $replace;

        /**
         * Get an easy to use instance of the class
         * @param string $subject
         * @return \self
         */
        public static function value($subject)
        {
            return new self($subject);
        }

        /**
         * Shortcut method: Replace all occurrences of the search string with the replacement
         * string where they appear outside quotes.
         * @param string $search
         * @param string $replace
         * @param string $subject
         * @return string
         */
        public static function str_replace_outside_quotes($search, $replace, $subject)
        {
            return self::value($subject)->replace_outside_quotes($search, $replace);
        }

        /**
         * Set the base string object
         * @param string $subject
         */
        public function __construct($subject)
        {
            $this->subject = (string) $subject;
        }

        /**
         * Replace all occurrences of the search string with the replacement
         * string where they appear outside quotes
         * @param string $search
         * @param string $replace
         * @return string
         */
        public function replace_outside_quotes($search, $replace)
        {
            $this->search = $search;
            $this->replace = $replace;
            return $this->_str_replace_outside_quotes();
        }

        /**
         * Validate an input string and perfOne a replace on all ocurrences
         * of $this->search with $this->replace
         * @return string
         */
        protected function _str_replace_outside_quotes()
        {
            $re_valid = '/
                # Validate string having embedded quoted substrings.
                ^                           # Anchor to start of string.
                (?:                         # Zero or more string chunks.
                  "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"  # Either a double quoted chunk,
                | \'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'  # or a single quoted chunk,
                | [^\'"\\\\]+               # or an unquoted chunk (no escapes).
                )*                          # Zero or more string chunks.
                \z                          # Anchor to end of string.
                /sx';

            if (!preg_match($re_valid, $this->subject)) {
                throw new OneStringException("Subject string is not valid in the replace_outside_quotes context.");
            }

            $re_parse = '/
                # Match one chunk of a valid string having embedded quoted substrings.
                  (                         # Either $1: Quoted chunk.
                    "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"  # Either a double quoted chunk,
                  | \'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'  # or a single quoted chunk.
                  )                         # End $1: Quoted chunk.
                | ([^\'"\\\\]+)             # or $2: an unquoted chunk (no escapes).
                /sx';

            return preg_replace_callback($re_parse, array($this, '_strReplaceOutsideQuotesCb'), $this->subject);
        }

        /**
         * Process each matching chunk from preg_replace_callback replacing
         * each occurrence of $this->search with $this->replace
         * @param array $matches
         * @return string
         */
        protected function _strReplaceOutsideQuotesCb($matches)
        {
            // Return quoted string chunks (in group $1) unaltered.
            if ($matches[1]) return $matches[1];
            // Process only unquoted chunks (in group $2).

            return preg_replace(
                '/' . preg_quote($this->search, '/') . '/',
                $this->replace,
                $matches[2]
            );
        }
    }

    /**
     * A result set class for working with collections of model instances
     */
    class OneResultSet implements Countable, IteratorAggregate, ArrayAccess, Serializable
    {
        /**
         * The current result set as an array
         * @var array
         */
        protected $_results = [];

        /**
         * Optionally set the contents of the result set by passing in array
         * @param array $results
         */
        public function __construct(array $results = [])
        {
            $this->set_results($results);
        }

        /**
         * Set the contents of the result set by passing in array
         * @param array $results
         */
        public function set_results(array $results)
        {
            $this->_results = $results;
        }

        /**
         * Get the current result set as an array
         * @return array
         */
        public function get_results()
        {
            return $this->_results;
        }

        /**
         * Get the current result set as an array
         * @return array
         */
        public function as_array()
        {
            return $this->get_results();
        }

        /**
         * Get the number of records in the result set
         * @return int
         */
        public function count()
        {
            return count($this->_results);
        }

        /**
         * Get an iterator for this object. In this case it supports foreaching
         * over the result set.
         * @return \ArrayIterator
         */
        public function getIterator()
        {
            return new ArrayIterator($this->_results);
        }

        /**
         * ArrayAccess
         * @param int|string $offset
         * @return bool
         */
        public function offsetExists($offset)
        {
            return isset($this->_results[$offset]);
        }

        /**
         * ArrayAccess
         * @param int|string $offset
         * @return mixed
         */
        public function offsetGet($offset)
        {
            return $this->_results[$offset];
        }

        /**
         * ArrayAccess
         * @param int|string $offset
         * @param mixed $value
         */
        public function offsetSet($offset, $value)
        {
            $this->_results[$offset] = $value;
        }

        /**
         * ArrayAccess
         * @param int|string $offset
         */
        public function offsetUnset($offset)
        {
            unset($this->_results[$offset]);
        }

        /**
         * Serializable
         * @return string
         */
        public function serialize()
        {
            return serialize($this->_results);
        }

        /**
         * Serializable
         * @param string $serialized
         * @return array
         */
        public function unserialize($serialized)
        {
            return unserialize($serialized);
        }

        /**
         * Call a method on all models in a result set. This allows for method
         * chaining such as setting a property on all models in a result set or
         * any other batch operation across models.
         * @example One::for_table('Widget')->find_many()->set('field', 'value')->save();
         * @param string $method
         * @param array $params
         * @return \OneResultSet
         */
        public function __call($method, $params = [])
        {
            foreach($this->_results as $model) {
                if (method_exists($model, $method)) {
                    call_user_func_array([$model, $method], $params);
                } else {
                    throw new OneMethodMissingException("Method $method() does not exist in class " . get_class($this));
                }
            }

            return $this;
        }
    }

    /**
     * A placeholder for exceptions eminating from the OneString class
     */
    class OneStringException extends Exception {}
    class OneMethodMissingException extends Exception {}
