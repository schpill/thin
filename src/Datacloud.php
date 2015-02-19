<?php
    /**
     * Project class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Datacloud
    {
        public static $_fields               = array();
        public static $_buffer               = false;
        public static $_cache                = false;
        public static $_numberOfQueries      = 0;
        public static $_totalDuration        = 0;
        public static $_urlAPI               = 'http://datacloud.zendgroup.com/';


        public static function getAll($type)
        {
            return json_decode(dwn(static::$_urlAPI . 'getAll/' . $type));
        }

        public static function makePath($type, $object)
        {
            $id = $object->getId();
            $dir = json_decode(dwn(static::$_urlAPI . 'checkDirectory/' . $type));
            return static::$_urlAPI . 'storage' . '/' . $dir . '/' . $id . '.data';
        }

        public static function getObject($object, $type = null)
        {
            if (!is_string($object)) {
                $id = isset($object->id) ? $object->id : null;
                if (null !== $id) {
                    return null;
                }
                $dir = json_decode(dwn(static::$_urlAPI . 'checkDirectory/' . $type));
                $object = static::$_urlAPI . 'storage' . '/' . $dir . '/' . $id . '.data';
            }
            $objectDwn = unserialize(dwn($object));
            return $objectDwn;
        }

        public static function getById($type, $id)
        {
            $objects = static::getAll($type);
            foreach ($objects as $tmpObject) {
                $tab = explode('/', $tmpObject);
                $keyComp = repl('.data', '', end($tab));
                if ($keyComp == $id) {
                    return static::getObject($tmpObject, $type);
                }
            }
            return null;
        }

        public static function add($type, $data = null)
        {
            if (null === $data) {
                $data = $_POST;
            }
            $key        = json_decode(dwn(static::$_urlAPI . 'makeKey/' . $type));
            $infos      = array('id' => $key, 'date_create' => time()) + $data;
            $newPost    = static::store($type, $infos, $key);
            return $key;
        }

        public static function edit($type, $id, $data = null)
        {
            if (null === $data) {
                $data = $_POST;
            }
            $object     = static::getById($type, $id);
            static::delete($type, $id);
            $infos      = array('id' => $id, 'date_create' => $object->getDateCreate()) + $data;
            $newPost    = static::store($type, $infos, $id);
            return $id;
        }

        public static function delete($type, $id)
        {
            $objects = static::getAll($type);
            foreach ($objects as $tmpObject) {
                $tab = explode('/', $tmpObject);
                $keyComp = repl('.data', '', end($tab));
                if ($keyComp == $id) {
                    return json_decode(dwn(static::$_urlAPI . 'delete/' . $type . '/' . $id));
                }
            }
            return false;
        }

        private static function post($type, $id, $object)
        {
            $postFields = array('data' => base64_encode($object));
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, static::$_urlAPI . 'store/' . $type . '/' . $id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            $result = curl_exec($ch);
            return json_decode($result);
        }

        public static function store($type, $flat, $key = null)
        {
            $object     = new $type;
            $object->populate($flat);
            $serialize  = serialize($object);
            if (is_null($key)) {
                $key    = json_decode(dwn(static::$_urlAPI . 'makeKey/' . $type));
            }
            $store = json_decode(dwn(static::$_urlAPI . 'store/' . $type . '/' . $key . '/' . base64_encode($serialize)));
            return $object;
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

        public static function query($type, $conditions = '', $offset = 0, $limit = 0, $orderField = null, $orderDirection = 'ASC')
        {
            static::_incQueries(static::_getTime());
            $queryKey  = sha1(serialize(func_get_args()));

            if (true === static::$_buffer) {
                $buffer = static::_buffer($queryKey);
                if (false !== $buffer) {
                    return $buffer;
                }
            }

            $results                = array();
            $resultsAnd             = array();
            $resultsOr              = array();
            $resultsXor             = array();

            $fields                 = static::getModel($type);
            $fields['id']           = array();
            $fields['date_create']  = array();
            if (!Arrays::isArray($orderField)) {
                if (null !== $orderField && !Arrays::exists($orderField, $fields)) {
                    $fields[$orderField] = array();
                }
            } else {
                foreach ($orderField as $tmpField) {
                    if (null !== $tmpField && !Arrays::exists($tmpField, $fields)) {
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

            if (true === static::$_buffer) {
                static::_buffer($queryKey, $results);
            }

            return $results;
        }

        public static function order($type, $results, $field = 'date_create', $orderDirection = 'ASC')
        {

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
            $fields = static::getModel($type);
            $new = array();
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
                $age = time() - filemtime($file);
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
    }
