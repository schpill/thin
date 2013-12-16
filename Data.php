<?php
    /**
     * Project class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Data
    {
        public static $_fields               = array();
        public static $_settings             = array();
        public static $_buffer               = false;
        public static $_cache                = false;
        public static $_numberOfQueries      = 0;
        public static $_totalDuration        = 0;


        public static function getAll($type)
        {
            $dir        = static::checkDir($type);
            $objects    = glob(STORAGE_PATH . DS . 'data' . DS . $dir . DS . '*.data');
            return $objects;
        }

        public static function makePath($type, $object)
        {
            $id = $object->getId();
            $dir = static::checkDir($type);
            return STORAGE_PATH . DS . 'data' . DS . $dir . DS . $id . '.data';
        }

        public static function getObject($pathObject, $type = null)
        {
            if (!is_string($pathObject)) {
                $id = isset($pathObject->id) ? $pathObject->id : null;
                if (null !== $id) {
                    return null;
                } else {
                    return $pathObject;
                }
            }
            $object = unserialize(fgc($pathObject));
            if (null === $type) {
                $type = Utils::cut(STORAGE_PATH . DS . 'data' . DS, DS, $pathObject);
                $type = substr($type, 0, -1);
            }
            $object->setThinType($type);
            return $object;
        }

        public static function getById($type, $id, $returnObject = true)
        {
            $dir    = static::checkDir($type);
            $file   = STORAGE_PATH . DS . 'data' . DS . $dir . DS . $id . '.data';
            if (File::exists($file)) {
                if (true === $returnObject) {
                    return static::getObject($file, $type);
                } else {
                    return $file;
                }
            }
            return null;
        }

        public static function add($type, $data = array())
        {
            if (empty($data)) {
                $data += $_POST;
            }
            $key        = static::makeKey($type);
            $infos      = array('id' => $key, 'date_create' => time()) + $data;
            $newPost    = static::store($type, $infos, $key);
            return $key;
        }

        public static function edit($type, $id, $data = array())
        {
            if (empty($data)) {
                $data += $_POST;
            }

            $object     = static::getById($type, $id);
            static::delete($type, $id);
            $infos      = array('id' => $id, 'date_create' => $object->getDateCreate()) + $data;
            $newPost    = static::store($type, $infos, $id);
            return $id;
        }

        public static function delete($type, $id)
        {
            $settings = ake($type, static::$_settings) ? static::$_settings[$type] : array();
            if (ake('relationships', $settings)) {
                $cardinalities = array('oneToOne', 'oneToMany', 'manyToMany');
                foreach ($settings['relationships'] as $field => $relationship) {
                    if (ake('type', $relationship) && ake('onDelete', $relationship)) {
                        if (Arrays::inArray($relationship['type'], $cardinalities) && 'cascade' == $relationship['onDelete']) {
                            $fieldType = substr($field, 0, -1);
                            $datastoDelete = static::query($fieldType, $type . ' = ' . $id);
                            if (count($datastoDelete)) {
                                foreach ($datastoDelete as $datatoDelete) {
                                    $objectToDelete = static::getById($fieldType, $datatoDelete->id, false);
                                    if (null !== $objectToDelete) {
                                        $del = File::delete($objectToDelete);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            static::event($type);
            $object = static::getById($type, $id, false);
            if (null !== $object) {
                return File::delete($object);
            }
            return false;
        }


        public static function store($type, $flat, $key = null)
        {
            static::event($type);
            $dir        = static::checkDir($type);
            $object     = new $type;
            $object->populate($flat);
            $serialize  = serialize($object);
            if (is_null($key)) {
                $key    = static::makeKey($type);
            }

            $file       = STORAGE_PATH . DS . 'data' . DS . $dir . DS . $key . '.data';
            File::delete($file);
            File::put($file, $serialize);
            return $object;
        }

        public static function makeKey($type, $keyLength = 9)
        {
            $dir    = static::checkDir($type);
            $key    = Inflector::quickRandom($keyLength);
            $check  = STORAGE_PATH . DS . 'data' . DS . $dir . DS . $key . '.data';
            if (File::exists($check)) {
                return static::makeKey($type);
            }
            return $key;
        }

        public static function checkDir($type)
        {
            $dirName = Inflector::lower($type . 's');
            $dir     = STORAGE_PATH . DS . 'data' . DS . $dirName;
            File::mkdir($dir, 0777);
            return $dirName;
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

        public static function query($type, $conditions = '', $offset = 0, $limit = 0, $orderField = 'date_create', $orderDirection = 'ASC')
        {
            static::_incQueries(static::_getTime());
            $queryKey  = sha1(serialize(func_get_args()));

            $cache = static::cache($type, $queryKey);

            if (null !== $cache) {
                return $cache;
            }

            $results                = array();
            $resultsAnd             = array();
            $resultsOr              = array();
            $resultsXor             = array();

            $fields                 = static::getModel($type);
            $fields['id']           = array();
            $fields['date_create']  = array();
            if (!Arrays::isArray($orderField)) {
                if (null !== $orderField && !ake($orderField, $fields)) {
                    $fields[$orderField] = array();
                }
            } else {
                foreach ($orderField as $tmpField) {
                    if (null !== $tmpField && !ake($tmpField, $fields)) {
                        $fields[$tmpField] = array();
                    }
                }
            }
            $datas                  = static::getAll($type);
            if(!count($datas)) {
                return $results;
            }

            if (!strlen($conditions)) {
                $conditionsAnd  = array();
                $conditionsOr   = array();
                $conditionsXor  = array();
                $results        = $datas;
            } else {
                $conditionsAnd  = explode(' && ',   $conditions);
                $conditionsOr   = explode(' || ',   $conditions);
                $conditionsXor  = explode(' XOR ',  $conditions);
            }

            if (count($conditionsOr) == count($conditionsAnd)) {
                if (current($conditionsOr) == current($conditionsAnd)) {
                    $conditionsAnd = array();
                }
            }

            if (count($conditionsXor) == count($conditionsOr)) {
                if (current($conditionsXor) == current($conditionsOr)) {
                    $conditionsXor = array();
                }
            }

            if (count($conditionsAnd)) {
                $empty = false;
                foreach ($conditionsAnd as $condition) {
                    foreach ($datas as $tmpObject) {
                        $object = static::getObject($tmpObject, $type);

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
                        } else {
                            $empty = true;
                        }
                    }
                }
                if (!count($results)) {
                    $results = $resultsAnd;
                } else {
                    $results = array_intersect($results, $resultsAnd);
                }
                if (true === $empty) {
                    $results = array();
                }
            }

            if (count($conditionsOr)) {
                foreach ($conditionsOr as $condition) {
                    foreach ($datas as $tmpObject) {
                        $object = static::getObject($tmpObject, $type);

                        $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                        $condition  = repl('NOT IN', 'NOTIN', $condition);

                        list($field, $op, $value) = explode(' ', $condition, 3);

                        $continue = true;

                        if (!isset($object->$field)) {
                            $continue = false;
                        } else {
                            if (null !== $object->$field) {
                                $continue = static::analyze($object->$field, $op, $value);
                            } else {
                                $continue = false;
                            }
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
                        $object = static::getObject($tmpObject, $type);

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

            if (count($results) && null !== $orderField) {
                if (Arrays::isArray($orderField)) {
                    $orderFields = $orderField;
                } else {
                    $orderFields = array($orderField);
                }
                foreach ($orderFields as $orderField) {
                    $sort = array();
                    foreach($results as $object) {
                        $objectCreated = static::getObject($object, $type);
                        foreach ($fields as $key => $infos) {
                            $value = isset($objectCreated->$key) ? $objectCreated->$key : null;
                            $sort[$key][] = $value;
                        }
                    }

                    $asort = array();
                    foreach ($sort as $key => $rows) {
                        for ($i = 0 ; $i < count($rows) ; $i++) {
                            if (empty($$key) || is_string($$key)) {
                                $$key = array();
                            }
                            $asort[$i][$key] = $rows[$i];
                            array_push($$key, $rows[$i]);
                        }
                    }

                    if ('ASC' == Inflector::upper($orderDirection)) {
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

            $cache = static::cache($type, $queryKey, $results);

            return $results;
        }

        public static function order($type, $results, $field = 'date_create', $orderDirection = 'ASC')
        {
            $queryKey   = sha1(serialize(func_get_args()));
            $cache      = static::cache($type, $queryKey);

            if (null !== $cache) {
                return $cache;
            }
            $fields                 = static::getModel($type);
            $fields['id']           = array();
            $fields['date_create']  = array();
            if (!Arrays::isArray($field)) {
                if (null !== $field && !ake($field, $fields)) {
                    $fields[$field] = array();
                }
            } else {
                foreach ($field as $tmpField) {
                    if (null !== $tmpField && !ake($tmpField, $fields)) {
                        $fields[$tmpField] = array();
                    }
                }
            }
            $sort = array();
            foreach($results as $object) {
                $path = static::makePath($type, $object);
                $objectCreated = static::getObject($path, $type);
                foreach ($fields as $key => $infos) {
                    $value = isset($objectCreated->$key) ? $objectCreated->$key : null;
                    $sort[$key][] = $value;
                }
            }

            $asort = array();
            foreach ($sort as $key => $rows) {
                for ($i = 0 ; $i < count($rows) ; $i++) {
                    if (empty($$key) || is_string($$key)) {
                        $$key = array();
                    }
                    $asort[$i][$key] = $rows[$i];
                    array_push($$key, $rows[$i]);
                }
            }

            if (Arrays::isArray($field) && Arrays::isArray($orderDirection)) {
                if (count($field) == 2) {
                    $first = current($field);
                    $second = end($field);
                    if ('ASC' == Inflector::upper(current($orderDirection)) && 'ASC' == Inflector::upper(end($orderDirection))) {
                        array_multisort($$first, SORT_ASC, $$second, SORT_ASC, $asort);
                    } elseif ('DESC' == Inflector::upper(current($orderDirection)) && 'ASC' == Inflector::upper(end($orderDirection))) {
                        array_multisort($$first, SORT_DESC, $$second, SORT_ASC, $asort);
                    } elseif ('DESC' == Inflector::upper(current($orderDirection)) && 'DESC' == Inflector::upper(end($orderDirection))) {
                        array_multisort($$first, SORT_DESC, $$second, SORT_DESC, $asort);
                    } elseif ('ASC' == Inflector::upper(current($orderDirection)) && 'DESC' == Inflector::upper(end($orderDirection))) {
                        array_multisort($$first, SORT_ASC, $$second, SORT_DESC, $asort);
                    }
                }
            } else {
                if ('ASC' == Inflector::upper($orderDirection)) {
                    array_multisort($$field, SORT_ASC, $asort);
                } else {
                    array_multisort($$field, SORT_DESC, $asort);
                }
            }
            $collection = array();
            foreach ($asort as $key => $row) {
                $tmpId = $row['id'];
                $tmpObject = static::getById($type, $tmpId);
                array_push($collection, $tmpObject);
            }
            $cache = static::cache($type, $queryKey, $collection);
            return $collection;
        }

        public static function analyze($comp, $op, $value)
        {
            if (isset($comp)) {
                $comp   = Inflector::lower($comp);
                $value  = Inflector::lower($value);
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
            if (ake($type, static::$_fields)) {
                return static::$_fields[$type];
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

        public static function update($type, $object, $newData)
        {
            $fields = static::$_fields[$type];
            $new    = array();
            $new['id'] = $object->id;
            $new['date_create'] = $object->date_create;
            foreach ($fields as $field => $info) {
                if (ake($field, $newData)) {
                    $new[$field] = $newData[$field];
                } else {
                    $value = !empty($object->$field) ? $object->$field : null;
                    $new[$field] = $value;
                }
            }
            foreach ($newData as $newField => $value) {
                if (!ake($newField, $fields)) {
                    $new[$newField] = $value;
                }
            }
            static::delete($type, $object->id);
            static::store($type, $new, $object->id);
            return static::getById($type, $object->id);
        }

        public static function cache($type, $key, $data = null)
        {
            $dir    = static::checkDir($type);
            $file  = STORAGE_PATH . DS . 'data' . DS . $dir . DS . $key . '.cache';
            if (File::exists($file)) {
                return unserialize(gzuncompress(File::get($file)));
            }
            if (null !== $data) {
                File::put($file, gzcompress(serialize($data), -1));
                return true;
            }
            return null;
        }

        public static function event($type)
        {
            $dir            = static::checkDir($type);
            $cachedFiles    = glob(STORAGE_PATH . DS . 'data' . DS . $dir . DS . '*.cache');
            if (count($cachedFiles)) {
                foreach ($cachedFiles as $cachedFile) {
                    $del = File::delete($cachedFile);
                }
            }
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

        private static function _getTime()
        {
            $time = microtime();
            $time = explode(' ', $time, 2);
            return ($time[1] + current($time));
        }

        private static function _incQueries($start)
        {
            static::$_numberOfQueries++;
            static::$_totalDuration += static::_getTime() - $start;
            Utils::set('NbQueriesNOSQL', static::$_numberOfQueries);
            Utils::set('SQLTotalDurationNOSQL', static::$_totalDuration);
        }

        public static function noBuffer()
        {
            static::$_buffer = false;
        }

        private static function _buffer($key, $data = null)
        {
            if (false === static::$_buffer) {
                return false;
            }
            $timeToBuffer = (false !== static::$_cache) ? static::$_cache * 60 : 2;
            $ext = (false !== static::$_cache) ? 'cache' : 'buffer';
            $file = CACHE_PATH . DS . $key . '_nosql.' . $ext;
            if (File::exists($file)) {
                $age = time() - File::modified($file);
                if ($age > $timeToBuffer) {
                    File::delete($file);
                } else {
                    return unserialize(fgc($file));
                }
            }
            if (null === $data) {
                return false;
            }
            File::put($file, serialize($data));
        }

        public static function evaluate($function, $params = null)
        {
            if (Arrays::isArray($params)) {
                foreach ($params as $key => $value) {
                    $function = repl("##$key##", $value, $function);
                }
            } else {
                if (null !== $params) {
                    $function = repl("##value##", $params, $function);
                }
            }
            return eval($function);
        }
    }
