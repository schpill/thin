<?php
    /**
     * Cachedata class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Cachedata
    {
        /**
         * The namespace to which the cache files should be written.
         *
         * @var string
         */
        protected $namespace;

        /**
         * @param  string  $namespace
         * @return void
         */
        public function __construct($namespace)
        {
            $this->_cleanCache();
            $this->namespace = $namespace;
        }

        private function _cleanCache()
        {
            $res = Data::query('cache', 'expiration <= ' . time());
            if (count($res)) {
                foreach ($res as $row) {
                    File::delete($row);
                }
            }
        }

        /**
         * Determine if an item exists in the cache.
         *
         * @param  string  $key
         * @return bool
         */
        public function has($key)
        {
            $this->_cleanCache();
            return (!is_null($this->get($key)));
        }

        /**
         * Retrieve an item from the cache driver.
         *
         * @param  string  $key
         * @return mixed
         */
        protected function retrieve($key)
        {
            $this->_cleanCache();
            $res = Data::query('cache', 'key = ' . $key . ' && namespace = ' . $this->namespace . ' && expiration > ' . time());
            if (!count($res)) {
                return null;
            }
            $obj = Data::getObject(current($res));

            return $obj->getValue();
        }

        /**
         * Write an item to the cache for a given number of minutes.
         *
         * @param  string  $key
         * @param  mixed   $value
         * @param  int     $minutes
         * @return void
         */
        public function put($key, $value, $minutes = 60)
        {
            $this->_cleanCache();
            if (0 >= $minutes) {
                return;
            }

            $this->forget($key);

            $expiration = $this->expiration($minutes);

            $data = array(
                'key'        => $key,
                'value'      => $value,
                'expiration' => $expiration,
                'namespace'  => $this->namespace,
            );
            Data::add('cache', $data);

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
            $this->_cleanCache();
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
            $this->_cleanCache();
            $res = Data::query('cache', 'key = ' . $key . ' && namespace = ' . $this->namespace);
            if (count($res)) {
                $obj = Data::getObject(current($res));
                $del = Data::delete('cache', $obj->getId());
            }
        }

        /**
         * Get an item from the cache.
         *
         * @param  string  $key
         * @param  mixed   $default
         * @return mixed
         */
        public function get($key, $default = null)
        {
            $this->_cleanCache();
            return (!is_null($item = $this->retrieve($key))) ? $item : Utils::value($default);
        }

        /**
         * Get an item from the cache, or cache and return the default value.
         * @param  string  $key
         * @param  mixed   $default
         * @param  int     $minutes
         * @param  string  $function
         * @return mixed
         */
        public function remember($key, $default, $minutes = 60, $function = 'put')
        {
            $this->_cleanCache();
            if (!is_null($item = $this->get($key, null))) {
                return $item;
            }

            $this->$function($key, $default = Utils::value($default), $minutes);

            return $default;
        }

        /**
         * Get an item from the cache, or cache the default value forever.
         *
         * @param  string  $key
         * @param  mixed   $default
         * @return mixed
         */
        public function sear($key, $default)
        {
            $this->_cleanCache();
            return $this->remember($key, $default, null, 'forever');
        }

        /**
         * Get the expiration time as a UNIX timestamp.
         *
         * @param  int  $minutes
         * @return int
         */
        protected function expiration($minutes)
        {
            $this->_cleanCache();
            return time() + ($minutes * 60);
        }
    }
