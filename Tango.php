<?php
	namespace Thin;

	class Tango
	{
		/**
		 * Holds the current Mongo connection object
		 *
		 * @var  Mongo
		 */
		protected $connection = false;

		/**
		 * Holds the current DB reference on the connection object
		 *
		 * @var  Object
		 */
		protected $db;

		/**
		 * Whether to use a persistent connection
		 *
		 * @var  bool
		 */
		protected $persist = false;

		/**
		 * Whether to use the profiler
		 *
		 * @var  bool
		 */
		protected $profiling = false;

		/**
		 * Holds all the select options
		 *
		 * @var  array
		 */
		protected $selects = array();

		/**
		 * Holds all the where options.
		 *
		 * @var  array
		 */
		public $wheres = array();

		/**
		 * Holds the sorting options
		 *
		 * @var  array
		 */
		protected $sorts = array();

		/**
		 * Holds the limit of the number of results to return
		 *
		 * @var  int
		 */
		protected $limit = 999999;

		/**
		 * The offset to start from.
		 *
		 * @var  int
		 */
		protected $offset = 0;

		/**
		 *	The class constructor
		 *	Automatically check if the Mongo PECL extension has been installed/enabled.
		 *	Generate the connection string and establish a connection to the MongoDB.
		 *
		 *	@param	array	$config		an array of config values
		 */
		public function __construct(array $config = array())
		{
			if (!class_exists('Mongo')) {
				throw new Exception("The MongoDB PECL extension has not been installed or enabled");
			}

			// Build up a connect options array for mongo
			$options = array("connect" => true);

			if (!empty($config['persistent'])) {
				$options['persist'] = 'thin_mongo_persist';
			}

			if (!empty($config['replicaset'])) {
				$options['replicaSet'] = $config['replicaset'];
			}

			$connection_string = "mongodb://";

			if (empty($config['hostname'])) {
				$config['hostname'] = '127.0.0.1';
			}

			if (empty($config['database'])) {
				throw new Exception("The database must be set to connect to MongoDB");
			}

			if ( ! empty($config['username']) and ! empty($config['password'])) {
				$connection_string .= "{$config['username']}:{$config['password']}@";
			}

			if (isset($config['port']) and ! empty($config['port'])) {
				$connection_string .= "{$config['hostname']}:{$config['port']}";
			} else {
				$connection_string .= "{$config['hostname']}";
			}


			$this->profiling = isAke($config, 'profiling', false);

			$connection_string .= "/{$config['database']}";

			// Let's give this a go
			try {
				$this->connection = new \MongoClient(trim($connection_string), $options);
				$this->db = $this->connection->{$config['database']};

				return $this;
			} catch (\MongoConnectionException $e) {
				throw new Exception("Unable to connect to MongoDB: {$e->getMessage()}", $e->getCode());
			}
		}

		/**
		 *	Drop a Mongo database
		 *
		 *	@param	string	$database		the database name
		 *	@usage	$mongodb->dropDb("foobar");
		 */
		public static function dropDb($database = null)
		{
			if (empty($database)) {
				throw new Exception('Failed to drop MongoDB database because name is empty');
			} else {
				try {
					static::instance()->connection->{$database}->drop();

					return true;
				} catch (\Exception $e) {
					throw new Exception("Unable to drop Mongo database `{$database}`: {$e->getMessage()}", $e->getCode());
				}
			}
		}

		/**
		 *	Drop a Mongo collection
		 *
		 *	@param	string	$db		the database name
		 *	@param	string	$col		the collection name
		 *	@usage	$mongodb->dropCollection('foo', 'bar');
		 */
		public static function dropCollection($db = '', $col = '')
		{
			if (empty($db)) {
				throw new Exception('Failed to drop MongoDB collection because database name is empty');
			}

			if (empty($col)) {
				throw new Exception('Failed to drop MongoDB collection because collection name is empty');
			} else {
				try {
					static::instance()->connection->{$db}->{$col}->drop();

					return true;
				} catch (\Exception $e) {
					throw new Exception("Unable to drop Mongo collection `{$col}`: {$e->getMessage()}", $e->getCode());
				}
			}
		}

		/**
		 *	Determine which fields to include OR which to exclude during the query process.
		 *	Currently, including and excluding at the same time is not available, so the
		 *	$includes array will take precedence over the $excludes array.  If you want to
		 *	only choose fields to exclude, leave $includes an empty array().
		 *
		 *	@param	array	$includes	which fields to include
		 *	@param	array	$excludes	which fields to exclude
		 *	@usage	$mongodb->select(array('foo', 'bar'))->get('foobar');
		 */
		public function select($includes = array(), $excludes = array())
		{
			if (!Arrays::is($includes)) {
				$includes = array($includes);
			}

			if (!Arrays::is($excludes)) {
				$excludes = array($excludes);
			}

			if (!empty($includes)) {
				foreach ($includes as $col) {
					$this->selects[$col] = 1;
				}
			} else {
				foreach ($excludes as $col) {
					$this->selects[$col] = 0;
				}
			}

			return $this;
		}

		/**
		 *	Get the documents based on these search parameters.  The $wheres array should
		 *	be an associative array with the field as the key and the value as the search
		 *	criteria.
		 *
		 *	@param	array	$wheres		an associative array with conditions, array(field => value)
		 *	@usage	$mongodb->where(array('foo' => 'bar'))->get('foobar');
		 */
		public function where($wheres = array())
		{
			foreach ($wheres as $wh => $val) {
				$this->wheres[$wh] = $val;
			}

			return $this;
		}

		/**
		 *	Get the documents where the value of a $field may be something else
		 *
		 *	@param	array	$wheres		an associative array with conditions, array(field => value)
		 *	@usage	$mongodb->orWhere(array( array('foo'=>'bar', 'bar'=>'foo' ))->get('foobar');
		 */
		public function orWhere($wheres = array())
		{
			if (count($wheres) > 0) {
				if ( ! isset($this->wheres['$or']) or ! Arrays::is($this->wheres['$or'])) {
					$this->wheres['$or'] = array();
				}

				foreach ($wheres as $wh => $val) {
					$this->wheres['$or'][] = array($wh => $val);
				}
			}

			return $this;
		}

		/**
		 *	Get the documents where the value of a $field is in a given $in array().
		 *
		 *	@param	string	$field		the field name
		 *	@param	array	$in			an array of values to compare to
		 *	@usage	$mongodb->whereIn('foo', array('bar', 'zoo', 'blah'))->get('foobar');
		 */
		public function whereIn($field = '', $in = array())
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$in'] = $in;

			return $this;
		}

		/**
		 *	Get the documents where the value of a $field is in all of a given $in array().
		 *
		 *	@param	string	$field		the field name
		 *	@param	array	$in			an array of values to compare to
		 *	@usage	$mongodb->whereInAll('foo', array('bar', 'zoo', 'blah'))->get('foobar');
		 */
		public function whereInAll($field = '', $in = array())
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$all'] = $in;

			return $this;
		}

		/**
		 *	Get the documents where the value of a $field is not in a given $in array().
		 *
		 *	@param	string	$field		the field name
		 *	@param	array	$in			an array of values to compare to
		 *	@usage	$mongodb->whereNotIn('foo', array('bar', 'zoo', 'blah'))->get('foobar');
		 */
		public function whereNotIn($field = '', $in = array())
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$nin'] = $in;

			return $this;
		}

		/**
		 *	Get the documents where the value of a $field is greater than $x
		 *
		 *	@param	string	$field		the field name
		 *	@param	mixed	$x			the value to compare to
		 *	@usage	$mongodb->whereGt('foo', 20);
		 */
		public function whereGt($field = '', $x)
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$gt'] = $x;

			return $this;
		}

		/**
		 *	Get the documents where the value of a $field is greater than or equal to $x
		 *
		 *	@param	string	$field		the field name
		 *	@param	mixed	$x			the value to compare to
		 *	@usage	$mongodb->whereGte('foo', 20);
		 */
		public function whereGte($field = '', $x)
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$gte'] = $x;

			return($this);
		}

		/**
		 *	Get the documents where the value of a $field is less than $x
		 *
		 *	@param	string	$field		the field name
		 *	@param	mixed	$x			the value to compare to
		 *	@usage	$mongodb->whereLt('foo', 20);
		 */
		public function whereLt($field = '', $x)
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$lt'] = $x;

			return($this);
		}

		/**
		 *	Get the documents where the value of a $field is less than or equal to $x
		 *
		 *	@param	string	$field		the field name
		 *	@param	mixed	$x			the value to compare to
		 *	@usage	$mongodb->whereLte('foo', 20);
		 */
		public function whereLte($field = '', $x)
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$lte'] = $x;

			return $this;
		}

		/**
		 *	Get the documents where the value of a $field is between $x and $y
		 *
		 *	@param	string	$field		the field name
		 *	@param	mixed	$x			the value to compare to
		 *	@param	mixed	$y			the high value to compare to
		 *	@usage	$mongodb->whereBetween('foo', 20, 30);
		 */
		public function whereBetween($field = '', $x, $y)
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$gte'] = $x;
			$this->wheres[$field]['$lte'] = $y;

			return $this;
		}

		/**
		 *	Get the documents where the value of a $field is between but not equal to $x and $y
		 *
		 *	@param	string	$field		the field name
		 *	@param	mixed	$x			the low value to compare to
		 *	@param	mixed	$y			the high value to compare to
		 *	@usage	$mongodb->whereBetweenNe('foo', 20, 30);
		 */
		public function whereBetweenNe($field = '', $x, $y)
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$gt'] = $x;
			$this->wheres[$field]['$lt'] = $y;

			return $this;
		}

		/**
		 *	Get the documents where the value of a $field is not equal to $x
		 *
		 *	@param	string	$field		the field name
		 *	@param	mixed	$x			the value to compare to
		 *	@usage	$mongodb->whereNe('foo', 1)->get('foobar');
		 */
		public function whereNe($field = '', $x)
		{
			$this->_whereInit($field);
			$this->wheres[$field]['$ne'] = $x;

			return $this;
		}

		/**
		 *	Get the documents nearest to an array of coordinates (your collection must have a geospatial index)
		 *
		 *	@param	string	$field		the field name
		 *	@param	array	$co			array of 2 coordinates
		 *	@usage	$mongodb->whereNear('foo', array('50','50'))->get('foobar');
		 */
		public function whereNear($field = '', $co = array())
		{
			$this->_whereInit($field);
			$this->where[$field]['$near'] = $co;

			return $this;
		}

		/**
		 *	--------------------------------------------------------------------------------
		 *	LIKE PARAMETERS
		 *	--------------------------------------------------------------------------------
		 *
		 *	Get the documents where the (string) value of a $field is like a value. The defaults
		 *	allow for a case-insensitive search.
		 *
		 *	@param $flags
		 *	Allows for the typical regular expression flags:
		 *		i = case insensitive
		 *		m = multiline
		 *		x = can contain comments
		 *		l = locale
		 *		s = dotall, "." matches everything, including newlines
		 *		u = match unicode
		 *
		 *	@param $disable_start_wildcard
		 *	If this value evaluates to false, no starting line character "^" will be prepended
		 *	to the search value, representing only searching for a value at the start of
		 *	a new line.
		 *
		 *	@param $disable_end_wildcard
		 *	If this value evaluates to false, no ending line character "$" will be appended
		 *	to the search value, representing only searching for a value at the end of
		 *	a line.
		 *
		 *	@usage	$mongodb->like('foo', 'bar', 'im', false, true);
		 */
		public function like($field = '', $value = '', $flags = 'i', $disable_start_wildcard = false, $disable_end_wildcard = false)
		{
			$field = (string) trim($field);
			$this->_whereInit($field);

			$value = (string) trim($value);
			$value = quotemeta($value);

			(bool) $disable_start_wildcard === false and $value = '^'.$value;
			(bool) $disable_end_wildcard === false and $value .= '$';

			$regex = "/$value/$flags";
			$this->wheres[$field] = new \MongoRegex($regex);

			return $this;
		}

		/**
		 *	Sort the documents based on the parameters passed. To set values to descending order,
		 *	you must pass values of either -1, false, 'desc', or 'DESC', else they will be
		 *	set to 1 (ASC).
		 *
		 *	@param	array	$fields		an associative array, array(field => direction)
		 */
		public function orderBy($fields = array())
		{
			foreach ($fields as $col => $val) {
				if ($val == -1 or $val === false or strtolower($val) == 'desc') {
					$this->sorts[$col] = -1;
				} else {
					$this->sorts[$col] = 1;
				}
			}

			return $this;
		}

		/**
		 *	Limit the result set to $x number of documents
		 *
		 *	@param	number	$x			the max amount of documents to fetch
		 *	@usage	$mongodb->limit($x);
		 */
		public function limit($x = 99999)
		{
			if ($x !== null and is_numeric($x) and $x >= 1) {
				$this->limit = (int) $x;
			}

			return $this;
		}

		/**
		 *	--------------------------------------------------------------------------------
		 *	OFFSET DOCUMENTS
		 *	--------------------------------------------------------------------------------
		 *
		 *	Offset the result set to skip $x number of documents
		 *
		 *	@param	number	$x			the number of documents to skip
		 *	@usage	$mongodb->offset($x);
		 */
		public function offset($x = 0)
		{
			if ($x !== null and is_numeric($x) and $x >= 1) {
				$this->offset = (int) $x;
			}

			return $this;
		}

		/**
		 *	Get the documents based upon the passed parameters
		 *
		 *	@param	string	$collection		the collection name
		 *	@param	array	$where			an array of conditions, array(field => value)
		 *	@param	number	$limit			the max amount of documents to fetch
		 *	@usage	$mongodb->getWhere('foo', array('bar' => 'something'));
		 */
		public function getWhere($collection = '', $where = array(), $limit = 99999)
		{
			return $this->where($where)->limit($limit)->get($collection);
		}

		/**
		 *	Get the document cursor from mongodb based upon the passed parameters
		 *
		 *	@param	string	$collection		the collection name
		 *	@usage	$mongodb->getCursor('foo', array('bar' => 'something'));
		 */
		public function getCursor($collection = "")
		{
			if (empty($collection)) {
				throw new Exception("In order to retrieve documents from MongoDB you must provide a collection name.");
			}

			$documents = $this->db->{$collection}
			->find($this->wheres, $this->selects)
			->limit((int) $this->limit)
			->skip((int) $this->offset)
			->sort($this->sorts);

			$this->_clear();

			return $documents;
		}

		/**
		 *	Get the documents based upon the passed parameters
		 *
		 *	@param	string	$collection		the collection name
		 *	@usage	$mongodb->get('foo', array('bar' => 'something'));
		 */
		public function get($collection = "")
		{

			$documents = $this->getCursor($collection);

			$returns = array();

			if ($documents && !empty($documents)) {
				foreach ($documents as $doc) {
					$returns[] = $doc;
				}
			}

			return $returns;
		}

		/**
		 * Get one document based upon the passed parameters
		 *
		 *	@param	string	$collection		the collection name
		 *	@usage	$mongodb->getOne('foo');
		 */
		public function getOne($collection = "")
		{
			if (empty($collection)) {
				throw new Exception("In order to retrieve documents from MongoDB you must provide a collection name.");
			}

			$returns = $this->db->{$collection}->findOne($this->wheres, $this->selects);

			$this->_clear();

			return $returns;
		}

		/**
		 *	Count the documents based upon the passed parameters
		 *
		 *	@param	string	$collection		the collection name
		 *	@param	boolean	$foundonly		send cursor limit and skip information to the count function, if applicable.
		 *	@usage	$mongodb->count('foo');
		 */

		public function count($collection = '', $foundonly = false)
		{
			if (empty($collection)) {
				throw new Exception("In order to retrieve a count of documents from MongoDB you must provide a collection name.");
			}

			$count = $this->db->{$collection}
			->find($this->wheres)
			->limit((int) $this->limit)
			->skip((int) $this->offset)
			->count($foundonly);

			$this->_clear();
			return ($count);
		}

		/**
		 *	--------------------------------------------------------------------------------
		 *	INSERT
		 *	--------------------------------------------------------------------------------
		 *
		 *	Insert a new document into the passed collection
		 *
		 *	@param	string	$collection		the collection name
		 *	@param	array	$insert			an array of values to insert, array(field => value)
		 *	@usage	$mongodb->insert('foo', $data = array());
		 */
		public function insert($collection = '', $insert = array())
		{
			if (empty($collection)) {
				throw new Exception("No Mongo collection selected to insert");
			}

			if (empty($insert) or ! Arrays::is($insert)) {
				throw new Exception("Nothing to insert into Mongo collection or insert value is not an array");
			}

			try {
				$this->db->{$collection}->insert($insert, array('fsync' => true));

				if (isset($insert['_id'])) {
					return $insert['_id'];
				} else {
					return false;
				}
			} catch (\MongoCursorException $e) {
				throw new Exception("Insert of data into MongoDB failed: {$e->getMessage()}", $e->getCode());
			}
		}

		/**
		 *	Updates a single document
		 *
		 *	@param	string	$collection		the collection name
		 *	@param	array	$data			an associative array of values, array(field => value)
		 *	@param	array	$options		an associative array of options
		 *	@usage	$mongodb->update('foo', $data = array());
		 */
		public function update($collection = '', $data = array(), $options = array(), $literal = false)
		{
			if (empty($collection)) {
				throw new Exception("No Mongo collection selected to update");
			}

			if (empty($data) or ! Arrays::is($data)) {
				throw new Exception("Nothing to update in Mongo collection or update value is not an array");
			}

			try {
				$options = array_merge($options, array('fsync' => true, 'multiple' => false));

				$this->db->{$collection}->update($this->wheres, (($literal) ? $data : array('$set' => $data)), $options);

				$this->_clear();

				return true;
			} catch (\MongoCursorException $e) {
				throw new Exception("Update of data into MongoDB failed: {$e->getMessage()}", $e->getCode());
			}
		}

		/**
		 *	Updates a collection of documents
		 *
		 *	@param	string	$collection		the collection name
		 *	@param	array	$data			an associative array of values, array(field => value)
		 *	@usage	$mongodb->updateAll('foo', $data = array());
		 */
		public function updateAll($collection = "", $data = array(), $literal = false)
		{
			if (empty($collection))
			{
				throw new Exception("No Mongo collection selected to update");
			}

			if (empty($data) || !Arrays::is($data)) {
				throw new Exception("Nothing to update in Mongo collection or update value is not an array");
			}

			try {
				$this->db->{$collection}->update($this->wheres, (($literal) ? $data : array('$set' => $data)), array('fsync' => true, 'multiple' => true));

				$this->_clear();
				return true;
			} catch (\MongoCursorException $e) {
				throw new Exception("Update of data into MongoDB failed: {$e->getMessage()}", $e->getCode());
			}
		}

		/**
		 *	Delete a document from the passed collection based upon certain criteria
		 *
		 *	@param	string	$collection		the collection name
		 *	@usage	$mongodb->delete('foo');
		 */
		public function delete($collection = '')
		{
			if (empty($collection)) {
				throw new Exception("No Mongo collection selected to delete from");
			}

			try
			{
				$this->db->{$collection}->remove($this->wheres, array('fsync' => true, 'justOne' => true));

				$this->_clear();

				return true;
			} catch (\MongoCursorException $e) {
				throw new Exception("Delete of data into MongoDB failed: {$e->getMessage()}", $e->getCode());
			}
		}

		/**
		 *	Delete all documents from the passed collection based upon certain criteria.
		 *
		 *	@param	string	$collection		the collection name
		 *	@usage	$mongodb->deleteAll('foo');
		 */
		public function deleteAll($collection = '')
		{
			if (empty($collection)) {
				throw new Exception("No Mongo collection selected to delete from");
			}

			try {
				$this->db->{$collection}->remove($this->wheres, array('fsync' => true, 'justOne' => false));

				$this->_clear();

				return true;
			} catch (\MongoCursorException $e) {
				throw new Exception("Delete of data from MongoDB failed: {$e->getMessage()}", $e->getCode());
			}
		}

		/**
		 *	Runs a MongoDB command (such as GeoNear). See the MongoDB documentation for more usage scenarios:
		 *	http://dochub.mongodb.org/core/commands
		 *
		 *	@param	array	$query	a query array
		 *	@usage	$mongodb->command(array('geoNear'=>'buildings', 'near'=>array(53.228482, -0.547847), 'num' => 10, 'nearSphere'=>TRUE));
		 */
		public function command($query = array())
		{
			try {
				$run = $this->db->command($query);
				return $run;
			} catch (\MongoCursorException $e) {
				throw new Exception("MongoDB command failed to execute: {$e->getMessage()}", $e->getCode());
			}
		}

		/**
		 *	Ensure an index of the keys in a collection with optional parameters. To set values to descending order,
		 *	you must pass values of either -1, false, 'desc', or 'DESC', else they will be
		 *	set to 1 (ASC).
		 *
		 *	@param	string	$collection		the collection name
		 *	@param	array	$keys			an associative array of keys, array(field => direction)
		 *	@param	array	$options		an associative array of options
		 *	@usage	$mongodb->addIndex($collection, array('first_name' => 'ASC', 'last_name' => -1), array('unique' => TRUE));
		 */
		public function addIndex($collection = '', $keys = array(), $options = array())
		{
			if (empty($collection)) {
				throw new Exception("No Mongo collection specified to add an index to");
			}

			if (empty($keys) || !Arrays::is($keys)) {
				throw new Exception("Index could not be created to MongoDB Collection because no keys were specified");
			}

			foreach ($keys as $col => $val) {
				if($val == -1 || $val === false || strtolower($val) == 'desc') {
					$keys[$col] = -1;
				} else {
					$keys[$col] = 1;
				}
			}

			if ($this->db->{$collection}->ensureIndex($keys, $options) == true) {
				$this->_clear();
				return $this;
			} else {
				throw new Exception("An error occured when trying to add an index to MongoDB Collection");
			}
		}

		/**
		 *	Remove an index of the keys in a collection. To set values to descending order,
		 *	you must pass values of either -1, false, 'desc', or 'DESC', else they will be
		 *	set to 1 (ASC).
		 *
		 *	@param	string	$collection		the collection name
		 *	@param	array	$keys			an associative array of keys, array(field => direction)
		 *	@usage	$mongodb->removeIndex($collection, array('first_name' => 'ASC', 'last_name' => -1));
		 */
		public function removeIndex($collection = '', $keys = array())
		{
			if (empty($collection)) {
				throw new Exception("No Mongo collection specified to remove an index from");
			}

			if (empty($keys) || !Arrays::is($keys)) {
				throw new Exception("Index could not be removed from MongoDB Collection because no keys were specified");
			}

			if ($this->db->{$collection}->deleteIndex($keys) == true) {
				$this->_clear();

				return $this;
			} else {
				throw new Exception("An error occured when trying to remove an index from MongoDB Collection");
			}
		}

		/**
		 *	Remove all indexes from a collection.
		 *
		 *	@param	string	$collection		the collection name
		 *	@usage	$mongodb->removeAllIndexes($collection);
		 */
		public function removeAllIndexes($collection = '')
		{
			if (empty($collection)) {
				throw new Exception("No Mongo collection specified to remove all indexes from");
			}

			$this->db->{$collection}->deleteIndexes();
			$this->_clear();

			return $this;
		}

		/**
		 *	Lists all indexes in a collection.
		 *
		 *	@param	string	$collection		the collection name
		 *	@usage	$mongodb->listIndexes($collection);
		 */
		public function listIndexes($collection = '')
		{
			if (empty($collection)) {
				throw new Exception("No Mongo collection specified to remove all indexes from");
			}

			return ($this->db->{$collection}->getIndexInfo());
		}

		/**
		 *	Returns a collection object so you can perform advanced queries, upserts, pushes and addtosets
		 *
		 *	@param	string	$collection		the collection name
		 *	@usage	$collection_name = $mongodb->getCollection('collection_name');
		 */
		public function getCollection($collection)
		{
			return ($this->db->{$collection});
		}

		/**
		 *	Returns all collection objects
		 *
		 *	@param	bool	$system_collections  whether or not to include system collections
		 *	@usage	$collections = $mongodb->listCollections();
		 */
		public function listCollections($system_collections = false)
		{
			return ($this->db->listCollections($system_collections));
		}

		/**
		 *	Resets the class variables to default settings
		 */
		protected function _clear()
		{
			$this->selects	= array();
			$this->wheres	= array();
			$this->limit	= 999999;
			$this->offset	= 0;
			$this->sorts	= array();
		}

		/**
		 *	Prepares parameters for insertion in $wheres array().
		 *
		 *	@param	string	$param		the field name
		 */
		protected function _whereInit($param)
		{
			if (!isset($this->wheres[$param])) {
				$this->wheres[ $param ] = array();
			}
		}
	}

