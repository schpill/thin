<?php
    namespace Thin;
    class Storage extends \SplObjectStorage
    {
       /**
        * @var array Array(index => hashcode)
        */
        private $map   = array();
        private $index = -1;

       /**
        * @param SplObjectStorage $storage
        */
       public function addAll(\SplObjectStorage $storage)
       {
            $storage->rewind();
            while($storage->valid()) {
                $this->offsetSet($storage->current(), $storage->getInfo());
                $storage->next();
            }
       }

       /**
        * Add an object to the storage
        * @param object $object
        * @param mixed $data
        */
       public function attach($object, $data = null)
       {
            $this->offsetSet($object, $data);
       }

       /**
        * Remove an object from the storage
        * @param object object
        */
       public function detach($object)
       {
            $this->offsetUnset($object);
       }

       /**
        * Add an object to the storage and set the internal pointer on it
        * @param object $object
        * @param mixed $data
        */
       public function offsetSet($object, $data = null)
       {
            if (! parent::contains($object)) {
                parent::attach($object, $data);
                $this->map[$this->index++] = spl_object_hash($object);
                while(parent::valid()) {
                    parent::next();
                }
            }
       }

       /**
        * Remove an object from the storage
        * @param object object
        */
       public function offsetUnset($object)
       {
            if (parent::contains($object)) {
                parent::offsetUnset($object);
                $index = array_search(spl_object_hash($object), $this->map, true);
                unset($this->map[$index]);
            }
       }

       /**
        * @param SplObjectStorage $storage
        */
       public function removeAll(\SplObjectStorage $storage)
       {
            $storage->rewind();
            while($storage->valid()) {
                $this->offsetUnset($storage->current());
                $storage->next();
            }
       }

       /**
        * @param SplObjectStorage $storage
        */
       public function removeAllExcept(\SplObjectStorage $storage)
       {
            parent::rewind();
            while(parent::valid()) {
                $current = parent::current();
                parent::next();
                if (!$storage->contains($current)) {
                    $this->offsetUnset($object);
                }
            }
       }

       /**
        * Returns the hashcode of the current object
        * @return string
        */
       public function getHash()
       {
            return spl_object_hash(parent::current());
       }

       /**
        * Returns the object identified by its hashcode
        * @param string $hash
        * @return object|null
        */
       public function getObjectByHash($hash)
       {
            $index = array_search($hash, $this->map, true);
            if (false !== $index) {
                parent::rewind();
                while(parent::valid()) {
                    if (parent::key() === $index) {
                        return parent::current();
                    }
                    parent::next();
                }
            }
       }

       /**
        * Returns the object identified by its position in the storage
        * @param int $key
        * @return object|null
        */
       public function getObjectByKey($key)
       {
            if (isset($this->map[$key])) {
                parent::rewind();
                while(parent::valid()) {
                    if (parent::key() === $key) {
                        return parent::current();
                    }
                    parent::next();
                }
            }
       }

       /**
        * Returns the object identified by its attached info
        * In case of many identical infos, the first attached object is returned
        * @param mixed $info
        * @return object|null
        */
       public function getObjectByInfo($info)
       {
            parent::rewind();
            while(parent::valid()) {
                if (parent::getInfo() === $info) {
                    return parent::current();
                }
                parent::next();
            }
       }

       /**
        * Returns the position of an object in the storage
        * Object identified by its hashcode
        * @param string $hash
        * @return int|null
        */
       public function getKeyByHash($hash)
       {
            $index = array_search($hash, $this->map, true);
            return (false === $index) ? null : $index;
       }

       /**
        * Returns the hashcode of an object identified by its position
        * @param int $key
        * @return string|null
        */
       public function getHashByKey($key)
       {
            return $this->map[$key];
       }

       /**
        * Returns the data associated with an object identified by its hashcode
        * @param string $hash
        * @return mixed|null
        */
       public function getInfoByHash($hash)
       {
            $index = array_search($hash, $this->map, true);
            if (false !== $index) {
                return $this->getInfoByKey($index);
            }
       }

       /**
        * Returns the data associated with an object identified by its position
        * @param int $key
        * @return mixed|null
        */
       public function getInfoByKey($key)
       {
            if (isset($this->map[$key])) {
                parent::rewind();
                while(parent::valid()) {
                    if (parent::key() === $key) {
                        return parent::getInfo();
                    }
                    parent::next();
                }
            }
       }

       /**
        * Move the internal pointer to an object identified by its hashcode
        * @param mixed $hash
        * @return bool false if the object doesn't exist
        */
       public function selectObjectByHash($hash)
       {
            $index = array_search($hash, $this->map, true);
            return (false !== $index) ? $this->selectObjectByKey($index) : false;
       }

       /**
        * Move the internal pointer to an object identified by its position in the storage
        * @param int $key
        * @return bool false if the object doesn't exist
        */
       public function selectObjectByKey($key)
       {
            if (isset($this->map[$key])) {
                parent::rewind();
                while(parent::valid()) {
                    if (parent::key() === $key) {
                        return true;
                    }
                    parent::next();
                }
            }
            return false;
       }

       /**
        * Select the object identified by its attached info
        * In case of many identical infos, the first attached object is selected
        * @param mixed $info
        * @return bool false if the object doesn't exist
        */
       public function selectObjectByInfo($info)
       {
            parent::rewind();
            while(parent::valid()) {
                if (parent::getInfo() === $info) {
                    return true;
                }
                parent::next();
            }
            return false;
       }

       /**
        * Returns the hashcode of the object in parameter
        * @param mixed $object
        * @return string
        */
       static public function computeHash($object)
       {
            return spl_object_hash($object);
       }

       /**
        * Returns the array of hashs
        * @return array array(position in the storage => hashcode)
        */
       public function allHashs()
       {
            return $this->map;
       }

       public static function merge()
       {
            $buffer = new self;
            if(func_num_args() > 0) {
                $args = func_get_args();
                foreach ($args as $objectStorage) {
                    foreach($objectStorage as $object) {
                        if(is_object($object)) {
                            $buffer->attach($object);
                        }
                    }
                }
            } else {
                return false;
            }
            return $buffer;
        }
    }
