<?php
    /**
     * Admin class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Admin
    {
        public static function getTypes()
        {
            $file = APPLICATION_PATH . DS . 'config' . DS . 'admin.php';
            $infos = include($file);
            list($types, $fields) = $infos;
            return $types;
        }

        public static function getFields($type)
        {
            $file = APPLICATION_PATH . DS . 'config' . DS . 'admin.php';
            $infos = include($file);
            list($types, $fields) = $infos;
            if (Arrays::exists($type, $fields)) {
                return $fields[$type];
            }
            return null;
        }

        public static function getAll($type)
        {
            $dir        = static::checkDir($type);
            $objects    = glob(STORAGE_PATH . DS . 'admin' . DS . $dir . DS . '*.' . Inflector::lower($type), GLOB_NOSORT);
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
            $session    = static::checkSession();
            $key        = static::makeKey($type);
            $infos      = array('id' => $key, 'author' => $session->getUser(), 'date_create' => time()) + $_POST;
            $newPost    = static::store($type, $infos, $key);
            Utils::go(URLSITE . 'admin/lists/' . Inflector::lower($type));
        }

        public static function edit($type, $id)
        {
            $session    = static::checkSession();
            $object     = static::getById($type, $id);
            static::delete($type, $id);
            $infos      = array('id' => $id, 'author' => $session->getUser(), 'date_create' => $object->getDateCreate()) + $_POST;
            $newPost    = static::store($type, $infos, $id);
            Utils::go(URLSITE . 'admin/lists/' . Inflector::lower($type));
        }

        public static function delete($type, $id)
        {
            $session = static::checkSession();
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
            $session    = static::checkSession();
            $dir        = static::checkDir($type);
            $object     = new $type;
            $object->populate($flat);
            $serialize  = serialize($object);
            if (is_null($key)) {
                $key    = static::makeKey($type);
            }

            $file       = STORAGE_PATH . DS . 'admin' . DS . $dir . DS . $key . '.' . Inflector::lower($type);
            File::delete($file);
            File::put($file, $serialize);
            return $object;
        }

        public static function makeKey($type)
        {
            $dir    = static::checkDir($type);
            $key    = Inflector::quickRandom(9);
            $check  = STORAGE_PATH . DS . 'admin' . DS . $dir . DS . $key . '.' . Inflector::lower($type);
            if (File::exists($check)) {
                return static::makeKey($type);
            }
            return $key;
        }

        private static function checkDir($type)
        {
            $dirName = Inflector::lower($type . 's');
            $dir     = STORAGE_PATH . DS . 'admin' . DS . $dirName;
            if (!is_dir($dir)) {
                mkdir($dir, 0777);
            }
            return $dirName;
        }

        private static function checkSession()
        {
            $session    = Session::instance('admin');
            if (null === $session) {
                Utils::go(URLSITE . 'admin/login');
                exit;
            }
            return $session;
        }

        public static function internalFunction($function)
        {
            return @eval('return ' . $function . ';');
        }

        public static function makeFormElement($field, $value, $fieldInfos, $type, $hidden = false)
        {
            if (true === $hidden) {
                return Form::hidden($field, $value, array('id' => $field));
            }
            $label = Html\Helper::display($fieldInfos['label']);
            $oldValue = $value;
            if (Arrays::exists('contentForm', $fieldInfos)) {
                if (!empty($fieldInfos['contentForm'])) {
                    $content = $fieldInfos['contentForm'];
                    $content = repl(array('##self##', '##field##', '##type##'), array($value, $field, $type), $content);

                    $value = static::internalFunction($content);
                }
            }
            if (true === is_string($value)) {
                $value = Html\Helper::display($value);
            }

            $type = $fieldInfos['fieldType'];
            $required = $fieldInfos['required'];

            switch ($type) {
                case 'select':
                    return Form::select($field, $value, $oldValue, array('id' => $field, 'required' => $required), $label);
                case 'password':
                    return Form::$type($field, array('id' => $field, 'required' => $required), $label);
                default:
                    return Form::$type($field, $value, array('id' => $field, 'required' => $required), $label);
            }
        }

        public static function query($type, $conditions = '', $offset = 0, $limit = 0, $orderField = null, $order = 'ASC')
        {
            $results        = array();
            $resultsAnd     = array();
            $resultsOr      = array();
            $resultsXor     = array();

            $datas          = static::getAll($type);
            if(!count($datas)) {
                return null;
            }

            if (strlen($conditions) == 0) {
                $conditionsAnd  = array();
                $conditionsOr   = array();
                $conditionsXor  = array();
                $results        = $datas;
            } else {
                $conditionsAnd  = explode(' && ', $conditions);
                $conditionsOr   = explode(' || ', $conditions);
                $conditionsXor  = explode(' XOR ', $conditions);
            }

            if (count($conditionsOr) == count($conditionsAnd)) {
                if (current($conditionsOr) == current($conditionsOr)) {
                    $conditionsOr = array();
                }
            }

            if (count($conditionsXor) == count($conditionsAnd)) {
                if (current($conditionsXor) == current($conditionsOr)) {
                    $conditionsXor = array();
                }
            }

            if (count($conditionsAnd)) {
                foreach ($conditionsAnd as $condition) {
                    foreach ($datas as $tmpObject) {
                        $object = static::getObject($tmpObject);

                        $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                        $condition  = repl('NOT IN', 'NOTIN', $condition);

                        list($field, $op, $value) = explode(' ', $condition, 3);

                        $continue = true;

                        if (null !== $object->$field) {
                            $continue = static::analyze($object->$field, $op, $value);
                        } else {
                            $continue = false;
                        }
                        if (true === $continue) {
                            array_push($resultsAnd, $object);
                        }

                    }
                }
                $results = array_intersect($results, $resultsAnd);
            }

            if (count($conditionsOr)) {
                foreach ($conditionsOr as $condition) {
                    foreach ($datas as $tmpObject) {
                        $object = static::getObject($tmpObject);

                        $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                        $condition  = repl('NOT IN', 'NOTIN', $condition);

                        list($field, $op, $value) = explode(' ', $condition, 3);

                        $continue = true;

                        if (null !== $object->$field) {
                            $continue = static::analyze($object->$field, $op, $value);
                        } else {
                            $continue = false;
                        }
                        if (true === $continue) {
                            array_push($resultsOr, $object);
                        }

                    }
                }
                $results = array_merge($results, $resultsOr);
            }

            if (count($conditionsXor)) {
                foreach ($conditionsXor as $condition) {
                    foreach ($datas as $tmpObject) {
                        $object = static::getObject($tmpObject);

                        $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                        $condition  = repl('NOT IN', 'NOTIN', $condition);

                        list($field, $op, $value) = explode(' ', $condition, 3);

                        $continue = true;

                        if (null !== $object->$field) {
                            $continue = static::analyze($object->$field, $op, $value);
                        } else {
                            $continue = false;
                        }
                        if (true === $continue) {
                            array_push($resultsXor, $object);
                        }

                    }
                }
                $results = array_merge(array_diff($results, $resultsXor), array_diff($resultsXor, $results));
            }

            if (count($results)) {
                if (0 < $limit) {
                    $max = count($results);
                    $number = $limit - $offset;
                    if ($number > $max) {
                        $offset = $max - $limit;
                        if (0 > $offset) {
                            $offset = 0;
                        }
                        $limit = $max;
                    }
                    $results = array_slice($results, $offset, $limit);
                }
            }

            if (count($results) && null !== $orderField) {
                $sort = array();
                foreach($results as $object) {
                    $tab = (array) $object;
                    foreach ($tab as $key => $value) {
                        $sort[$key][] = $value;
                    }
                }
                $asort = array();
                foreach ($sort as $key => $rows) {
                    for ($i = 0 ; $i < count($rows) ; $i++) {
                        if (!is_array($$key)) {
                            $$key = array();
                        }
                        $asort[$i][$key] = $rows[$i];
                        array_push($$key, $rows[$i]);
                    }
                }

                if ('ASC' == Inflector::upper($order)) {
                    array_multisort($$orderField, SORT_ASC, $asort);
                } else {
                    array_multisort($$orderField, SORT_DESC, $asort);
                }
                $collection = array();
                foreach ($asort as $key => $row) {
                    $tmpId = $row['id'];
                    $tmpObject = static::getById($type, $tmpId);
                    array_push($collection, static::getObject($tmpObject));
                }

                $results = $collection;
            }

            return $results;
        }

        private static function analyze($comp, $op, $value)
        {
            if (isset($comp)) {
                $comp = Inflector::lower($comp);
                $value = Inflector::lower($value);
                switch ($op) {
                    case '=':
                        if ($comp == $value) {
                            return true;
                        }
                        break;
                    case '>=':
                        if ($comp >= $value) {
                            return true;
                        }
                        break;
                    case '>':
                        if ($comp > $value) {
                            return true;
                        }
                        break;
                    case '<':
                        if ($comp < $value) {
                            return true;
                        }
                        break;
                    case '<=':
                        if ($comp <= $value) {
                            return true;
                        }
                        break;
                    case '<>':
                    case '!=':
                        if ($comp <> $value) {
                            return true;
                        }
                        break;
                    case 'LIKE':
                        $value = repl('"', '', $value);
                        $value = repl('%', '', $value);
                        if (strstr($comp, $value)) {
                            return true;
                        }
                        break;
                    case 'NOTLIKE':
                        $value = repl('"', '', $value);
                        $value = repl('%', '', $value);
                        if (!strstr($comp, $value)) {
                            return true;
                        }
                        break;
                    case 'IN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        if (in_array($comp, $tabValues)) {
                            return true;
                        }
                        break;
                    case 'NOTIN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        if (!in_array($comp, $tabValues)) {
                            return true;
                        }
                        break;
                }
            }
            return false;
        }

        public static function __callstatic($method, $args)
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
