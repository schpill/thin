<?php
    /**
     * Newsletter class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Newsletter
    {
        public static function getAll($type)
        {
            $dir        = static::checkDir($type);
            $objects    = glob(STORAGE_PATH . DS . 'newsletters' . DS . $dir . DS . '*.' . Inflector::lower($type));
            return $objects;
        }

        public static function getObject($object)
        {
            return unserialize(fgc($object));
        }

        public static function getById($type, $id)
        {
            $objects = static::getAll($type);
            foreach ($objects as $tmpObject) {
                $tab = explode(DS, $tmpObject);
                $keyComp = repl('.' . Inflector::lower($type), '', end($tab));
                if ($keyComp == $id) {
                    return static::getObject($tmpObject);
                }
            }
            return null;
        }

        public static function add($type)
        {
            $session    = Session::instance('newsletter');
            $key        = static::makeKey($type);
            $infos      = array('id' => $key, 'author' => $session->getUser(), 'dateCreate' => $date) + $_POST;
            $newPost    = static::store($type, $infos, $key);
            Utils::go(URLSITE . 'newsletter/list/' . Inflector::lower($type));
        }

        public static function edit($type, $id)
        {
            $session    = Session::instance('newsletter');
            $object     = static::getById($type, $id);
            static::delete($type, $id);
            $infos      = array('id' => $id, 'author' => $session->getUser(), 'dateCreate' => $object->dateCreate) + $_POST;
            $newPost    = static::store($type, $infos, $id);
            Utils::go(URLSITE . 'newsletter/list/' . Inflector::lower($type));
        }

        public static function delete($type, $id)
        {
            $objects = static::getAll($type);
            foreach ($objects as $tmpObject) {
                $tab = explode(DS, $tmpObject);
                $keyComp = repl('.' . Inflector::lower($type), '', end($tab));
                if ($keyComp == $id) {
                    return File::delete($tmpObject);
                }
            }
            return false;
        }


        public static function store($type, $flat, $key = null)
        {
            $dir    = static::checkDir($type);
            $object = new $type;
            $object->populate($flat);
            $serialize = serialize($object);
            if (is_null($key)) {
                $key = static::makeKey($type);
            }

            $file = STORAGE_PATH . DS . 'newsletters' . DS . $dir . DS . $key . '.' . Inflector::lower($type);
            File::delete($file);
            File::put($file, $serialize);
            return $object;
        }

        public static function makeKey($type)
        {
            $dir    = static::checkDir($type);
            $key    = Inflector::quickRandom(9);
            $check  = STORAGE_PATH . DS . 'newsletters' . DS . $dir . DS . $key . '.' . Inflector::lower($type);
            if (File::exists($check)) {
                return static::makeKey($type);
            }
            return $key;
        }

        private static function checkDir($type)
        {
            $dirName = Inflector::lower($type . 's');
            $dir     = STORAGE_PATH . DS . 'newsletters' . DS . $dir;
            if (!is_dir($dir)) {
                mkdir($dir, 0777);
            }
            return $dirName;
        }

        public static function makeView($campaign, $user)
        {
            $dirStatsViews  = STORAGE_PATH . DS . 'newsletters' . DS . 'stats' . DS . 'views';
            $dirCampaign    = $dirStatsViews . DS . $campaign->getId();
            if (!is_dir($dirCampaign)) {
                mkdir($dirCampaign, 0777);
            }
            $dirUser = $dirCampaign . DS . $user->getId();
            if (!is_dir($dirUser)) {
                mkdir($dirUser, 0777);
            }
            $count    = glob($dirUser . DS . '*.count', GLOB_NOSORT);
            if (!count($count)) {
                $key        = Inflector::quickRandom(9);
                $newCount   = $dirUser . DS . $key . '.count';
                $initialize = new viewCount;
                $initialize->setId($key);
                $initialize->setCampaign($campaign->getId());
                $initialize->setUser($user->getId());
                $initialize->setCount(0);
                $initialize->setDates(array());
                $serialize  = serialize($initialize);
                File::delete($newCount);
                File::put($newCount, $serialize);
                return $initialize;
            } else {
                return static::getObject(current($count));
            }
        }

        public function addView($idView)
        {
            $dirStatsViews  = STORAGE_PATH . DS . 'newsletters' . DS . 'stats' . DS . 'views';
            $counts         = glob($dirStatsViews . DS . '*.count', GLOB_NOSORT);
            foreach ($counts as $count) {
                $tab = explode(DS, $count);
                $keyComp = repl('.count', '', end($tab));
                if ($keyComp == $idView) {
                    $thisCount = static::getObject($count);
                    $dates = $thisCount->getDates();
                    array_push($dates, time());
                    $new = new viewCount;
                    $new->setId($idView);
                    $new->setCampaign($thisCount->getCampaign());
                    $new->setUser($thisCount->getUser());
                    $new->setCount($thisCount->getCount() + 1);
                    $new->setDates($dates);
                    $serialize  = serialize($new);
                    File::delete($count);
                    File::put($count, $serialize);
                    return $new;
                }
            }
        }

        public static function __call($method, $args)
        {
            if (substr($method, 0, strlen('add')) == 'add') {
                $type = Inflector::lower(repl('add', '', $method));
                return static::add($type);
            } elseif (substr($method, 0, strlen('edit')) == 'edit') {
                $type = Inflector::lower(repl('edit', '', $method));
                return static::edit($type, current($args));
            } elseif (substr($method, 0, strlen('delete')) == 'delete') {
                $type = Inflector::lower(repl('delete', '', $method));
                return static::delete($type, current($args));
            } elseif (substr($method, 0, strlen('find')) == 'find') {
                $type = Inflector::lower(repl('find', '', $method));
                return static::getById($type, current($args));
            }
        }
    }
