<?php
    namespace Thin;

    class Arr implements \ArrayAccess, \Iterator
    {
        private $cache, $cacheKeys, $cacheCount, $cursor = 0, $first = true;

        public function __construct()
        {
            $this->cache        = 'memory.tab.' . str_replace('-', '.', Utils::uuid());
            $this->cacheKeys    = str_replace('memory.tab.', 'memory.tab.keys.', $this->cache);
            $this->cacheCount   = str_replace('memory.tab.', 'memory.tab.count.', $this->cache);
        }

        public function count()
        {
            return redis()->get($this->cacheCount);
        }

        public function __destruct()
        {
            redis()->del($this->cache);
            redis()->del($this->cacheKeys);
            redis()->del($this->cacheCount);
        }

        public function rewind()
        {
            $this->cursor = 0;
        }

        public function current()
        {
            $key = redis()->hget($this->cacheKeys, $this->cursor);

            return unserialize(redis()->hget($this->cache, $key));
        }

        public function next()
        {
            $this->cursor++;
        }

        public function offsetExists($index)
        {
            return strlen(redis()->hget($this->cacheKeys, $index)) ? true : false;
        }

        public function offsetSet($key, $value)
        {
            $index = false === $this->first ? $this->count() : 0;

            $key = !strlen($key) ? $index : $key;

            redis()->hset($this->cache, $key, serialize($value));
            redis()->hset($this->cacheKeys, $index, $key);
            redis()->incr($this->cacheCount);

            $this->first = false;
        }

        public function offsetGet($key)
        {
            return unserialize(redis()->hget($this->cache, $key));
        }

        public function offsetUnset($key)
        {
            redis()->hdel($this->cache, $key);

            $keys = redis()->hgetall($this->cacheKeys);

            foreach ($keys as $ind => $value) {
                if ($value == $key) {
                    redis()->hdel($this->cacheKeys, $ind);
                    redis()->hdel($this->cache, $key);
                    break;
                }
            }

            redis()->decr($this->cacheCount);
        }

        public function key()
        {
            return redis()->hget($this->cacheKeys, $this->cursor);
        }

        public function seek($position)
        {
            $this->cursor = $position;
        }

        public function valid()
        {
            $valid = strlen(redis()->hget($this->cacheKeys, $this->cursor)) ? true : false;

            return $valid;
        }

        public function first()
        {
            $key = redis()->hget($this->cacheKeys, 0);

            return [$key => unserialize(redis()->hget($this->cache, $key))];
        }

        public function last()
        {
            $key = redis()->hget($this->cacheKeys, $this->count() - 1);

            return [$key => unserialize(redis()->hget($this->cache, $key))];
        }
    }
