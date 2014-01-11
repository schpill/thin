<?php
    /**
     * Project class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Project
    {
        public static function getAll($type)
        {
            $dir        = static::checkDir($type);
            $objects    = glob(STORAGE_PATH . DS . 'project' . DS . $dir . DS . '*.' . Inflector::lower($type), GLOB_NOSORT);
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
            Utils::go(URLSITE . 'project/list/' . Inflector::lower($type));
        }

        public static function edit($type, $id)
        {
            $session    = static::checkSession();
            $object     = static::getById($type, $id);
            static::delete($type, $id);
            $infos      = array('id' => $id, 'author' => $session->getUser(), 'date_create' => $object->getDateCreate()) + $_POST;
            $newPost    = static::store($type, $infos, $id);
            Utils::go(URLSITE . 'project/list/' . Inflector::lower($type));
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

            $file       = STORAGE_PATH . DS . 'project' . DS . $dir . DS . $key . '.' . Inflector::lower($type);
            File::delete($file);
            File::put($file, $serialize);
            return $object;
        }

        public static function makeKey($type)
        {
            $dir    = static::checkDir($type);
            $key    = Inflector::quickRandom(9);
            $check  = STORAGE_PATH . DS . 'project' . DS . $dir . DS . $key . '.' . Inflector::lower($type);
            if (File::exists($check)) {
                return static::makeKey($type);
            }
            return $key;
        }

        private static function checkDir($type)
        {
            $dirName = Inflector::lower($type . 's');
            $dir     = STORAGE_PATH . DS . 'project' . DS . $dirName;
            File::mkdir($dir, 0777);
            return $dirName;
        }

        private static function checkSession()
        {
            $session    = Session::instance('project');
            if (null === $session) {
                Utils::go(URLSITE . 'project/login');
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
            if (ake('contentForm', $fieldInfos)) {
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
            $results                = array();
            $resultsAnd             = array();
            $resultsOr              = array();
            $resultsXor             = array();

            $fields                 = static::getModel($type);
            $fields['id']           = array();
            $fields['author']       = array();
            $fields['date_create']  = array();
            $datas                  = static::getAll($type);
            if(!count($datas)) {
                return null;
            }

            if (!strlen($conditions)) {
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
                if (current($conditionsXor) == current($conditionsAnd)) {
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
                            if (!count($resultsAnd)) {
                                array_push($resultsAnd, $tmpObject);
                            } else {
                                $tmpResult = array($tmpObject);
                                $resultsAnd = array_intersect($resultsAnd, $tmpResult);
                            }
                        }

                    }
                }
                if (!count($results)) {
                    $results = $resultsAnd;
                } else {
                    $results = array_intersect($results, $resultsAnd);
                }
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
                            if (!count($resultsOr)) {
                                array_push($resultsOr, $tmpObject);
                            } else {
                                $tmpResult = array($tmpObject);
                                $resultsOr = array_merge($resultsOr, $tmpResult);
                            }
                        }

                    }
                }
                if (!count($results)) {
                    $results = $resultsOr;
                } else {
                    $results = array_merge($results, $resultsOr);
                }
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
                            if (!count($resultsXor)) {
                                array_push($resultsXor, $tmpObject);
                            } else {
                                $tmpResult = array($tmpObject);
                                $resultsXor = array_merge(array_diff($resultsXor, $tmpResult), array_diff($tmpResult, $resultsXor));
                            }
                        }

                    }
                }
                if (!count($results)) {
                    $results = $resultsXor;
                } else {
                    $results = array_merge(array_diff($results, $resultsXor), array_diff($resultsXor, $results));
                }
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
                    $object = static::getObject($object);
                    foreach ($fields as $key => $infos) {
                        $value = $object->$key;
                        $sort[$key][] = $value;
                    }
                }

                $asort = array();
                foreach ($sort as $key => $rows) {
                    for ($i = 0 ; $i < count($rows) ; $i++) {
                        if (empty($$key)) {
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
                    array_push($collection, $tmpObject);
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
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (strstr($comp, $value)) {
                            return true;
                        }
                        break;
                    case 'NOTLIKE':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (!strstr($comp, $value)) {
                            return true;
                        }
                        break;
                    case 'LIKESTART':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        return (substr($comp, 0, strlen($value)) === $value);
                        break;
                    case 'LIKEEND':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (!strlen($comp)) {
                            return true;
                        }
                        return (substr($comp, -strlen($value)) === $value);
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

        private static function getModel($type)
        {
            $file = APPLICATION_PATH . DS . 'entities' . DS . 'project' . DS . ucfirst(Inflector::lower($type)) . '.php';
            if (File::exists($file)) {
                $model = include($file);
                return $model['fields'];
            }
            return array();
        }

        public static function makeQuery($queryJs, $type)
        {
            $queryJs = substr($queryJs, 9, -2);

            $query = repl('##', ' && ', $queryJs);
            $query = repl('%%', ' ', $query);
            return $query;
        }

        public static function makeQueryDisplay($queryJs, $type)
        {
            $fields = static::getModel($type);

            $queryJs = substr($queryJs, 9, -2);
            $query = repl('##', ' AND ', $queryJs);
            $query = repl('%%', ' ', $query);

            $query = repl('NOT LIKE', 'ne contient pas', $query);
            $query = repl('LIKESTART', 'commence par', $query);
            $query = repl('LIKEEND', 'finit par', $query);
            $query = repl('LIKE', 'contient', $query);
            $query = repl('%', '', $query);

            foreach ($fields as $field => $fieldInfos) {
                if (strstr($query, $field)) {
                    if (strlen($fieldInfos['content'])) {
                        $seg = Utils::cut($field, " '", $query);
                        $segs = explode(" '", $query);
                        for ($i = 0 ; $i < count($segs) ; $i++) {
                            $seg = trim($segs[$i]);
                            if (strstr($seg, $field)) {
                                $goodSeg = trim($segs[$i + 1]);
                                list($oldValue, $dummy) = explode("'", $goodSeg, 2);
                                $content = repl(array('##self##', '##type##', '##field##'), array($oldValue, $type, $field), $fieldInfos['content']);
                                $value = Html\Helper::display(static::internalFunction($content));
                                $newSeg = repl("$oldValue'", "$value'", $goodSeg);
                                $query = repl($goodSeg, $newSeg, $query);
                            }
                        }
                    }
                    $query = repl($field, Inflector::lower($fieldInfos['label']), $query);
                }
            }
            $query = repl('=', 'vaut', $query);
            $query = repl('<', 'plus petit que', $query);
            $query = repl('>', 'plus grand que', $query);
            $query = repl('>=', 'plus grand ou vaut', $query);
            $query = repl('<=', 'plus petit ou vaut', $query);
            $query = repl(' AND ', ' et ', $query);
            $query = repl(" '", ' <span style="color: #ffdd00;">', $query);
            $query = repl("'", '</span>', $query);

            return $query;
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
