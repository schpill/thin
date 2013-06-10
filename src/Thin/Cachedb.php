<?php
    namespace Thin;
    class Cachedb
    {
        /**
         * The cache key from the cache configuration file.
         *
         * <code>
         * $config = new cacheConfig;
         * $config->populate(array('entity' => 'thin', 'table' => 'eav'));
         * $cache = new Cachedb('application', $config);
         * $test = $cache->remember('mamie', 'merci');
         * </code>
         *
         * @var string
         */
        protected $_namespace;
        protected $config;

        /**
         * Create a new database cache driver instance.
         *
         * @param  string  $key
         * @return void
         */
        public function __construct($_namespace, $config)
        {
            $this->config = $config;
            $this->_namespace = $_namespace;
        }

        /**
         * Determine if an item exists in the cache.
         *
         * @param  string  $key
         * @return bool
         */
        public function has($key)
        {
            return (!is_null($this->get($key)));
        }

        /**
         * Get an item from the cache.
         *
         * <code>
         *      // Get an item from the cache driver
         *      $name = Cache::driver('name');
         *
         *      // Return a default value if the requested item isn't cached
         *      $name = Cache::get('name', 'Taylor');
         * </code>
         *
         * @param  string  $key
         * @param  mixed   $default
         * @return mixed
         */
        public function get($key, $default = null)
        {
            return (!is_null($item = $this->retrieve($key))) ? $item : value($default);
        }

        /**
         * Retrieve an item from the cache driver.
         *
         * @param  string  $key
         * @return mixed
         */
        protected function retrieve($key)
        {
            $cache = $this->db()->select("entity_name = '" . md5($this->_namespace) . "' AND table_name = '" . md5($key) . "'", true);

            if (!empty($cache)) {
                $data = unserialize($cache->getData());
                $infos = explode('%%%%%:::::', $data);
                if (time() >= current($infos)) {
                    return $this->forget($key);
                }

                return end($infos);
            }
            return null;
        }

        /**
         * Write an item to the cache for a given number of minutes.
         *
         * <code>
         *      // Put an item in the cache for 15 minutes
         *      Cachedb::put('name', 'Taylor', 15);
         * </code>
         *
         * @param  string  $key
         * @param  mixed   $value
         * @param  int     $minutes
         * @return Thin\Cachedb
         */
        public function put($key, $value, $minutes)
        {
            $cache = $this->db()->select("entity_name = '" . md5($this->_namespace) . "' AND table_name = '" . md5($key) . "'", true);
            if (empty($cache)) {
                $cache = em($this->config->entity, $this->config->table);
            } else {
                $cache->delete();
                $cache = em($this->config->entity, $this->config->table);
            }
            $data       = serialize(($this->expiration($minutes) + time()) . '%%%%%:::::' . $value);

            $cache->setDateAdd(date('Y-m-d H:i:s'))->setEntityName(md5($this->_namespace))->setTableName(md5($key))->setTableId(999)->setData($data);
            $newCache = $cache->save();
            return $this;
        }

        /**
         * Write an item to the cache for five years.
         *
         * @param  string  $key
         * @param  mixed   $value
         * @return void
         */
        public function forever($key, $value)
        {
            return $this->put($key, $value, 2628000);
        }

        /**
         * Delete an item from the cache.
         *
         * @param  string  $key
         * @return void
         */
        public function forget($key)
        {
            $cache = $this->db()->select("entity_name = '" . md5($this->_namespace) . "' AND table_name = '" . md5($key) . "'", true);
            if (!empty($cache)) {
                $cache->delete();
            }
        }

        /**
         * Get a query builder for the database table.
         *
         * @return Thin\Orm
         */
        protected function db()
        {
            return em($this->config->entity, $this->config->table);
        }

        /**
         * Get the expiration time as a UNIX timestamp.
         *
         * @param  int  $minutes
         * @return int
        */
        protected function expiration($minutes)
        {
            return time() + ($minutes * 60);
        }

        /**
         * Get an item from the cache, or cache and return the default value.
         *
         * <code>
         *      // Get an item from the cache, or cache a value for 15 minutes
         *      $name = Cachedb::remember('name', 'Taylor', 15);
         *
         *      // Use a closure for deferred execution
         *      $count = Cachedb::remember('count', function() { return User::count(); }, 15);
         * </code>
         *
         * @param  string  $key
         * @param  mixed   $default
         * @param  int     $minutes
         * @param  string  $function
         * @return mixed
         */
        public function remember($key, $default, $minutes = 60, $function = 'put')
        {
            if (!is_null($item = $this->get($key, null))) {
                return $item;
            }

            $this->$function($key, $default = value($default), $minutes);

            return $default;
        }

    }
