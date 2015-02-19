<?php
    namespace Thin;

    class Dba {

        const HANDLER = 'flatfile';
        private $dbHandler, $cache = [], $useCache = true;

        public function __construct($database, $readonly = true, $useCache = true)
        {
            $this->useCache = $useCache;
            $file_exists    = File::exists($database);

            if (!$file_exists) {
                $readonly = false;
            }

            if ($readonly) {
                $opt = 'rl';

                if (!is_readable($database)) {
                    throw new Exception('database is not readable: ' . $database);
                    return false;
                }
            } else {
                $opt = 'cl';

                if ($file_exists) {
                    if (!is_writable($database)) {
                        throw new Exception('database is not writeable: ' . $database);
                        return false;
                    }
                } else {
                    if (!is_writable(dirname($database))) {
                        throw new Exception('database is not inside a writeable directory: ' . $database);
                        return false;
                    }
                }
            }

            $this->dbHandler = dba_open(
                $database,
                $opt,
                self::HANDLER
            );

            if (!$this->dbHandler) {
                throw new Exception('cannot open database: ' . $database);
                return false;
            }

            return $this;
        }


        public function close()
        {
            dba_close($this->dbHandler);

            $this->dbHandler = false;
            $this->cache = [];
        }

        public function get($key)
        {
            if ($this->useCache && Arrays::exists($key, $this->cache)) {
                return $this->cache[$key];
            }

            $v = dba_fetch($key, $this->dbHandler);

            // convert
            $value = $this->decode($v);

            // store
            if ($this->useCache) {
                $this->cache[$key] = $value;
            }

            return $value;
        }

        public function set($key, $value) {
            // Store
            if ($this->useCache) {
                $this->cache[$key] = $value;
            }

            // Convert
            $v = $this->encode($value);

            // Write
            if (dba_exists($key, $this->dbHandler)) {
                $r = dba_replace($key, $v, $this->dbHandler);
            } else {
                $r = dba_insert($key, $v, $this->dbHandler);
            }

            return $r;
        }

        public function delete($key)
        {
            unset($this->cache[$key]);

            return dba_delete($key, $this->dbHandler);
        }

        public function flush()
        {
            $this->cache = [];

            return dba_sync($this->dbHandler);
        }

        public function getAll()
        {
            // reset cache
            $this->cache = [];
            $tmp = [];
            // read all
            for (
                $key = dba_firstkey($this->dbHandler);
                $key != false;
                $key = dba_nextkey($this->dbHandler)
            ) {
                $v = dba_fetch($key, $this->dbHandler);
                // Convert
                $value = $this->decode($v);
                // Store
                $tmp[$key] = $value;
            }

            if ($this->useCache) {
                $this->cache = $tmp;
            }

            return $tmp;
        }

        public function isValid($key)
        {
            return dba_exists($key, $this->dbHandler);
        }

        public function optimize()
        {
            $this->cache = [];

            return dba_optimize($this->dbHandler);
        }

        public function encode($v)
        {
            return json_encode($v);
        }

        public function decode($v)
        {
            return json_decode($v);
        }

        public function debug()
        {
            $html   = [];

            $html[] = "Available DBA handlers:\n<ul>\n";

            foreach (dba_handlers(true) as $handler_name => $handler_version) {
                // clean the versions
                $handler_version = str_replace('$', '', $handler_version);
                $html[] = "<li>$handler_name: $handler_version</li>\n";
            }

            $html[] = "</ul>\n";

            $html[] = "All opened databases:\n<ul>\n";

            foreach (dba_list() as $res_id => $db_name) {
                // clean the versions
                $html[] = "<li>$res_id : $db_name</li>\n";
            }

            $html[] = "</ul>\n";

            return $html;
        }

        public function getCache()
        {
            return $this->cache;
        }
    }
