<?php
    /**
     * Cache class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Cache
    {
        /**
         * The path to which the cache files should be written.
         *
         * @var string
         */
        protected $path;

        /**
         * @param  string  $path
         * @return void
         */
        public function __construct($path)
        {
            $this->path = $path;
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
         * Retrieve an item from the cache driver.
         *
         * @param  string  $key
         * @return mixed
         */
        protected function retrieve($key)
        {
            if (!File::exists($this->path . $key)) {
                return null;
            }

            // File based caches store have the expiration timestamp stored in
            // UNIX format prepended to their contents. We'll compare the
            // timestamp to the current time when we read the file.
            if (time() >= substr($cache = fgc($this->path . $key), 0, 10)) {
                return $this->forget($key);
            }

            return unserialize(substr($cache, 10));
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
            if ($minutes <= 0) {
                return;
            }

            $value = $this->expiration($minutes) . serialize($value);

            file_put_contents($this->path . $key, $value, LOCK_EX);
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
            if (File::exists($this->path . $key)) {
                unlink($this->path . $key);
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
        public function remember($key, $default, $minutes, $function = 'put')
        {
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
            return time() + ($minutes * 60);
        }
    }
