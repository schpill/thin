<?php
    namespace Thin;

    /**
     * A storage using APC.
     */
    class APCStorage
    {
        private $masterKey;

        public function __construct($db, $table)
        {
            $this->masterKey = "$db::$table";
        }

        public function set($key, $data, $ttl = 0)
        {
            return $this->store($key, $data, $ttl);
        }

        public function setex($key, $data, $ttl)
        {
            $ttl = $ttl * 60;

            return $this->store($key, $data, $ttl);
        }

        public function store($key, $data, $ttl = 0)
        {
            apc_store($this->masterKey . '::' . $key, $data, $ttl);

            return $this;
        }

        public function get($key, $default = null)
        {
            return $this->retrieve($key, $default);
        }

        public function retrieve($key, $default = null)
        {
            $rc = apc_fetch($this->masterKey . '::' . $key, $success);

            return $success ? $rc : $default;
        }

        public function del($key)
        {
            return $this->forget($key);
        }

        public function delete($key)
        {
            return $this->forget($key);
        }

        public function forget($key)
        {
            apc_delete($this->masterKey . '::' . $key);

            return $this;
        }

        public function flush()
        {
            return $this->clear();
        }

        public function clear()
        {
            apc_clear_cache();

            return $this:
        }

        public function exists($key)
        {
            return apc_exists($this->masterKey . '::' . $key);
        }
    }
