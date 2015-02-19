<?php
    /**
     * Project class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    use SQLite3;
    class Data
    {
        public static $_fields               = array();
        public static $_settings             = array();
        public static $_rights               = array();
        public static $_all                  = array();
        public static $_indexes              = array();
        public static $_objects              = array();
        public static $_transactions         = array();
        public static $_buffer               = false;
        public static $_cache                = false;
        public static $_numberOfQueries      = 0;
        public static $_totalDuration        = 0;
        public static $_db                   = array();
        public static $sql;


        public static function newOne($type, $data = array())
        {
            $settings   = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $fields     = Arrays::exists($type, static::$_fields)
            ? static::$_fields[$type]
            : static::noConfigFields($type);

            $obj = new objData;
            $obj->thin_type = $type;
            if (count($fields) && count($data) && Arrays::isAssoc($data)) {
                foreach ($fields as $key => $infos) {
                    $value = (Arrays::exists($key, $data)) ? $data[$key] : null;
                    $obj->$key = (Arrays::is($value) && Arrays::isAssoc($value)) ? Arrays::setObject($value) : $value;
                }
            }
            return $obj;
        }

        public static function db($type)
        {
            $fields = Arrays::exists($type, static::$_fields)
            ? static::$_fields[$type]
            : static::noConfigFields($type);
            $db = empty(static::$sql) ? new SQLite3(':memory:') : static::$sql;
            $q = "DROP TABLE IF EXISTS $type; CREATE TABLE $type (id VARCHAR PRIMARY KEY, date_create";
            if (count($fields)) {
                foreach ($fields as $field => $infos) {
                    $q .= ", $field";
                }
            }
            $q .= ");";
            $db->exec($q);
            static::$_db[$type] = $db;
        }

        public static function getAll($type)
        {
            $dir        = static::checkDir($type);
            static::_clean(STORAGE_PATH . DS . 'data' . DS . $dir . DS . 'write', $type);
            $objects    = glob(STORAGE_PATH . DS . 'data' . DS . $dir . DS . 'read' . DS . '*.data', GLOB_NOSORT);

            if (!$objects) {
                $objects = array();
            }

            if (!count($objects)) {
                static::event($type);
            }
            return $objects;
        }

        public static function first($type)
        {
            $all = static::getAll($type);
            if (count($all)) {
                $first = Arrays::first($all);
                return static::getObject($first, $type);
            }
            return static::newOne($type);
        }

        public static function last($type)
        {
            $all = static::getAll($type);
            if (count($all)) {
                $first = Arrays::last($all);
                return static::getObject($first, $type);
            }
            return static::newOne($type);
        }

        private static function _clean($directory, $type)
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $versioning     = false;

            if (Arrays::exists('versioning', $settings)) {
                $versioning = $settings['versioning'];
            }

            $firstDirectory = repl(DS . 'write', '', $directory);
            $readDirectory  = repl(DS . 'write', DS . 'read', $directory);
            $firstFiles     = glob($firstDirectory . DS . '*.data', GLOB_NOSORT);

            if (!$firstFiles) {
                $firstFiles = array();
            }

            if (count($firstFiles)) {
                foreach ($firstFiles as $firstFile) {
                    $tab = explode(DS, $firstFile);
                    $deplacedFile = $readDirectory . DS . Arrays::last($tab);
                    File::put($deplacedFile, static::load($firstFile));
                    File::delete($firstFile);
                }
            }

            $files = glob($directory . DS . '*.data', GLOB_NOSORT);

            if (!$files) {
                $files = array();
            }

            if (count($files)) {
                $collection = array();
                foreach ($files as $file) {
                    $tab        = explode(DS, $file);
                    $fileName   = Arrays::last($tab);
                    list($id, $timestamp) = explode('_', repl('.data', '', $fileName), 2);
                    if (!Arrays::exists($id, $collection)) {
                        $collection[$id] = $timestamp;
                    } else {
                        if ($collection[$id] < $timestamp) {
                            $oldTimestamp       = $collection[$id];
                            $oldFile            = repl('_' . $timestamp, '_' . $oldTimestamp, $file);
                            File::delete($oldFile);
                            $collection[$id]    = $timestamp;
                        } else {
                            File::delete($file);
                        }
                    }
                }
                foreach ($collection as $id => $timestamp) {
                    $fileRead       = $readDirectory . DS . $id . '.data';
                    $file           = $directory . DS . $id . '_' . $timestamp . '.data';
                    $fileVersion    = repl(DS . 'write', DS . 'versions', $file);
                    if (File::exists($fileRead) && false !== $versioning) {
                        File::put($fileVersion, static::load($fileRead));
                        File::delete($fileRead);
                    }
                    File::put($fileRead, static::load($file));
                    File::delete($file);
                }
            }
        }

        public static function indexes($type)
        {
            if (!Arrays::exists($type, static::$_indexes)) {
                $collection = array();
                $objects    = static::getAll($type);
                if (count($objects)) {
                    foreach ($objects as $path) {
                        $collection[] = static::getObject($path, $type);
                    }
                }
                static::$_indexes[$type] = $collection;
            }
            return static::$_indexes[$type];
        }

        public static function makePath($type, $object)
        {
            $id     = $object->getId();
            $dir    = static::checkDir($type);
            return STORAGE_PATH . DS . 'data' . DS . $dir . DS . 'read' . DS . $id . '.data';
        }

        public static function getIt($type, $path)
        {
            return static::getObject($path, $type);
        }

        public static function getObject($pathObject, $type = null)
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook           = Arrays::exists('getObject', $settings)
            ? $settings['getObject']
            : null;
            static::_hook($hook, func_get_args(), 'before');
            if (!is_string($pathObject)) {
                $id = isset($pathObject->id) ? $pathObject->id : null;
                if (null !== $id) {
                    return null;
                } else {
                    return $pathObject;
                }
            }
            $object = dataDecode($pathObject);
            if (null === $type) {
                $type = Inflector::lower(Utils::cut(STORAGE_PATH . DS . 'data' . DS, DS, $pathObject));
                $type = substr($type, 0, -1);
            }
            $object->setThinType($type);
            static::_hook($hook, func_get_args(), 'after');
            return $object;
        }

        public static function getById($type, $id, $returnObject = true)
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook           = Arrays::exists('getById', $settings)
            ? $settings['getById']
            : null;
            static::_hook($hook, func_get_args(), 'before');
            $dir    = static::checkDir($type);
            static::_clean(STORAGE_PATH . DS . 'data' . DS . $dir . DS . 'write', $type);
            $file   = STORAGE_PATH . DS . 'data' . DS . $dir . DS . 'read' . DS . $id . '.data';
            if (true === File::exists($file)) {
                if (true === $returnObject) {
                    static::_hook($hook, func_get_args(), 'after');
                    return static::getObject($file, $type);
                } else {
                    static::_hook($hook, func_get_args(), 'after');
                    return $file;
                }
            }
            static::_hook($hook, func_get_args(), 'after');
            return null;
        }

        public static function add($type, $data = array())
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $fields         = Arrays::exists($type, static::$_fields)
            ? static::$_fields[$type]
            : static::noConfigFields($type);
            $checkTuple     = Arrays::exists('checkTuple', $settings)
            ? $settings['checkTuple']
            : null;
            $hook           = Arrays::exists('add', $settings)
            ? $settings['add']
            : null;
            static::_hook($hook, func_get_args(), 'before');

            if (Arrays::exists('beforeAdd', $settings)) {
                $settings['beforeAdd']($type, $data);
            }

            if (empty($data) && 0 < count($_POST)) {
                $data += $_POST;
            }

            if (count($fields)) {
                foreach ($fields as $field => $info) {
                    $val = Arrays::exists($field, $data) ? $data[$field] : null;
                    if (empty($val)) {
                        if (!Arrays::exists('canBeNull', $info)) {
                            if (!Arrays::exists('default', $info)) {
                                static::_hook($hook, func_get_args(), 'after');
                                throw new Exception('The field ' . $field . ' cannot be null.');
                            } else {
                                $data[$field] = $info['default'];
                            }
                        }
                    }
                    if (Arrays::exists('checkValue', $info)) {
                        $closure = $info['checkValue'];
                        if ($closure instanceof \Closure) {
                            $data[$field] = $closure($val);
                        }
                    }
                }
            }

            $key        = static::makeKey($type);
            $infos      = array('id' => $key, 'date_create' => time()) + $data;
            $newPost    = static::store($type, $infos, $key);
            if (Arrays::exists('afterAdd', $settings)) {
                $settings['afterAdd']($type, $data, $newPost);
            }
            static::_hook($hook, func_get_args(), 'after');
            return $key;
        }

        public static function edit($type, $id, $data = array())
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $checkTuple     = Arrays::exists('checkTuple', $settings)
            ? $settings['checkTuple']
            : null;
            $hook           = Arrays::exists('edit', $settings)
            ? $settings['edit']
            : null;
            static::_hook($hook, func_get_args(), 'before');
            $fields         = Arrays::exists($type, static::$_fields)
            ? static::$_fields[$type]
            : static::noConfigFields($type);

            if (empty($data) && count($_POST)) {
                $data += $_POST;
            }

            if (Arrays::exists('beforeEdit', $settings)) {
                $settings['beforeEdit']($type, $id, $data);
            }
            if (count($fields)) {
                foreach ($fields as $field => $info) {
                    $val = $data[$field];
                    if (empty($val)) {
                        if (!Arrays::exists('canBeNull', $info)) {
                            static::_hook($hook, func_get_args(), 'after');
                            throw new Exception('The field ' . $field . ' cannot be null.');
                        }
                    }
                    if (Arrays::exists('checkValue', $info)) {
                        $closure = $info['checkValue'];
                        if ($closure instanceof \Closure) {
                            $data[$field] = $closure($val);
                        }
                    }
                }
            }

            $object     = static::getById($type, $id);
            $infos      = array('id' => $id, 'date_create' => $object->getDateCreate()) + $data;

            $newPost    = static::store($type, $infos, $id);

            if (Arrays::exists('afterEdit', $settings)) {
                $settings['afterEdit']($type, $data, $newPost);
            }
            static::_hook($hook, func_get_args(), 'after');
            return $id;
        }

        public static function delete($type, $id)
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $indexes        = Arrays::exists('indexes', $settings)
            ? $settings['indexes']
            : null;
            $hook           = Arrays::exists('delete', $settings)
            ? $settings['delete']
            : null;
            static::_hook($hook, func_get_args(), 'before');


            if (Arrays::exists('beforeDelete', $settings)) {
                $settings['beforeDelete']($type, $id);
            }

            if (Arrays::exists('relationships', $settings)) {
                $cardinalities = array('oneToOne', 'oneToMany', 'manyToMany');
                foreach ($settings['relationships'] as $field => $relationship) {
                    if (Arrays::exists('type', $relationship) && Arrays::exists('onDelete', $relationship)) {
                        if (Arrays::in($relationship['type'], $cardinalities) && 'cascade' == $relationship['onDelete']) {
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

            if (count($indexes)) {
                $oldObject = static::getById($type, $id);
                foreach ($indexes as $indexField => $indexInfo) {
                    static::indexRemove($indexField, $indexInfo, $oldObject);
                }
            }

            static::event($type);
            $object = static::getById($type, $id, false);
            if (null !== $object) {
                static::_hook($hook, func_get_args(), 'after');
                if (Arrays::exists('afterDelete', $settings)) {
                    if ($settings['afterDelete'] instanceof \Closure) {
                        $settings['afterDelete']($type, $id);
                    }
                }
                return File::delete($object);
            }
            static::_hook($hook, func_get_args(), 'after');
            return false;
        }

        public static function _sanitize($what)
        {
            if (is_string($what)) {
                return html_entity_decode($what);
            }
            if (is_object($what)) {
                return $what;
            }
            if (Arrays::is($what)) {
                $newWhat = array();
                foreach ($what as $key => $value) {
                    if (!is_object($value)) {
                        if (!Arrays::is($value)) {
                            $newWhat[$key] = html_entity_decode($value, ENT_COMPAT, 'utf-8');
                        } else {
                            $newWhat[$key] = array_map('Thin\Data::_sanitize', $value);
                        }
                    }
                }
                return $newWhat;
            }
            return $what;
        }

        public static function store($type, $flat, $key = null)
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook           = Arrays::exists('store', $settings)
            ? $settings['store']
            : null;
            static::_hook($hook, func_get_args(), 'before');
            $checkTuple     = Arrays::exists('checkTuple', $settings)
            ? $settings['checkTuple']
            : null;
            $indexes        = Arrays::exists('indexes', $settings)
            ? $settings['indexes']
            : null;


            if (Arrays::exists('beforeStore', $settings)) {
                $settings['beforeDelete']($type, $flat);
            }

            static::event($type);
            $dir        = static::checkDir($type);
            $object     = new $type;
            $flat       = static::_sanitize($flat);
            $object->populate($flat);
            $serialize  = static::serialize($object);
            $edit       = !is_null($key);
            $add        = !$edit;
            if (true === $add) {
                $key    = static::makeKey($type);
            }

            $versionFile    = STORAGE_PATH . DS . 'data' . DS . $dir . DS . 'versions' . DS . $key . '_' . Timer::getMS() . '.data';

            if (true === $edit) {
                $oldFile        = STORAGE_PATH . DS . 'data' . DS . $dir . DS . 'read' . DS . $key . '.data';
                $oldObject = static::getIt($type, $oldFile);
                if (count($indexes)) {
                    foreach ($indexes as $indexField => $indexInfo) {
                        static::indexRemove($indexField, $indexInfo, static::getObject($oldFile));
                    }
                }
                if (File::exists($oldFile)) {
                    File::delete($oldFile);
                }
            }

            if (!empty($checkTuple)) {
                if (is_string($checkTuple)) {
                    $db     = dm($type);
                    $res = $db->where($checkTuple . ' = ' . $flat[$checkTuple])->get();
                }
                if (Arrays::is($checkTuple)) {
                    $query  = '';
                    foreach ($checkTuple as $ct) {
                        $query .= $ct . ' = ' . $flat[$ct] . ' && ';
                    }
                    $query  = substr($query, 0, -4);

                    $tabConditions  = explode(' && ', $query);
                    $init           = true;
                    foreach ($tabConditions as $cond) {
                        $db          = dm($type);
                        $res         = $db->where($cond)->sub();
                        if (true === $init) {
                            $init    = false;
                            $results = $res;
                        } else {
                            $results = array_intersect($results, $res);
                        }
                    }
                    $db     = dm($type);
                    $res    = $db->get($results);
                }
                if (count($res)) {
                    $row = $db->first($res);
                    static::_hook($hook, array("thin_type" => $type) + $flat, 'after');
                    return $row;
                }
            }

            $file = repl(DS . 'versions' . DS, DS . 'write' . DS, $versionFile);
            File::delete($file);
            File::put($file, $serialize);

            $fields     = Arrays::exists($type, static::$_fields)
            ? static::$_fields[$type]
            : static::noConfigFields($type);
            $versioning = false;

            if (count($fields)) {
                foreach ($fields as $field => $info) {
                    $val = $object->$field;
                    if (empty($val)) {
                        if (!Arrays::exists('canBeNull', $info)) {
                            if (!Arrays::exists('default', $info)) {
                                static::_hook($hook, func_get_args(), 'after');
                                throw new Exception('The field ' . $field . ' cannot be null.');
                            } else {
                                $object->$field = $info['default'];
                            }
                        }
                    } else {
                        if (Arrays::exists('sha1', $info)) {
                            if (!preg_match('/^[0-9a-f]{40}$/i', $val) || strlen($val) != 40) {
                                $object->$field = sha1($val);
                            }
                        } elseif (Arrays::exists('md5', $info)) {
                            if (!preg_match('/^[0-9a-f]{32}$/i', $val) || strlen($val) != 32) {
                                $object->$field = md5($val);
                            }
                        }
                    }
                }
            }

            if (Arrays::exists('versioning', $settings)) {
                $versioning = $settings['versioning'];
            }

            if (false !== $versioning) {
                File::put($versionFile, $serialize);
            }

            if (count($indexes)) {
                $thisObject = static::getById($type, $object->getId());
                foreach ($indexes as $indexField => $indexInfo) {
                    static::indexCreate($indexField, $indexInfo, $thisObject);
                }
                static::commit();
            }


            if (Arrays::exists('afterStore', $settings)) {
                if ($settings['afterStore'] instanceof \Closure) {
                    $settings['afterStore']($type, $flat);
                }
            }

            static::_hook($hook, func_get_args(), 'after');
            static::emptyCache($type);
            return $object;
        }

        public static function indexRemove($indexField, $indexInfo, $object)
        {
            $type       = $object->thin_type;
            $settings   = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook       = Arrays::exists('indexRemove', $settings)
            ? $settings['indexRemove']
            : null;
            static::_hook($hook, func_get_args(), 'before');
            $dirName    = static::checkDir($type);
            $indexDir   = STORAGE_PATH . DS . 'data' . DS . $dirName . DS . 'indexes';
            $file = $indexDir . DS . $indexField . DS . md5($object->$indexField) . DS . $object->id . '.data';
            if (File::exists($file)) {
                File::delete($file);
            }
            static::_hook($hook, func_get_args(), 'after');
        }

        public static function indexCreate($indexField, $indexInfo, $object)
        {
            $type       = $object->thin_type;
            $settings   = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook       = Arrays::exists('indexCreate', $settings)
            ? $settings['indexCreate']
            : null;
            static::_hook($hook, func_get_args(), 'before');
            $dirName    = static::checkDir($type);
            $indexDir   = STORAGE_PATH . DS . 'data' . DS . $dirName . DS . 'indexes';
            File::mkdir($indexDir . DS . $indexField, 0777);

            $typeIndex = Arrays::exists('type', $indexInfo) ? $indexInfo['type'] : 'none';

            if ('fulltext' == $typeIndex) {
                $words = static::prepareFulltext($object->$indexField);
                if (count($words)) {
                    foreach ($words as $word) {
                        static::transaction('fileIndex', $indexDir, $indexField, $word, $object->id);
                    }
                }
            } elseif ('unique' == $typeIndex) {
                $dirIndex = $indexDir . DS . $indexField . DS . md5($object->$indexField);
                $continue = true;
                if (is_dir($dirIndex)) {
                    $objectsDir = glob($dirIndex . DS . '*.data', GLOB_NOSORT);

                    if (!$objectsDir) {
                        $objectsDir = array();
                    }

                    $continue   = 1 > count($objectsDir);
                }
                if (true === $continue) {
                    static::transaction('fileIndex', $indexDir, $indexField, $object->$indexField, $object->id);
                } else {
                    $object->delete();
                    static::_hook($hook, func_get_args(), 'after');
                    throw new Exception($indexField . ' est un index unique pour ' . $type . ".");
                }
            } else {
                static::transaction('fileIndex', $indexDir, $indexField, $object->$indexField, $object->id);
            }
            static::_hook($hook, func_get_args(), 'after');
        }

        private static function transaction($method)
        {
            $params = array_slice(func_get_args(), 1);
            array_push(static::$_transactions, array($method, $params));
        }

        private static function commit()
        {
            if (count(static::$_transactions)) {
                foreach (static::$_transactions as $transaction) {
                    list($method, $params) = $transaction;
                    $commit = call_user_func_array(array('Thin\\Data', $method), $params);
                }
                static::$_transactions = array();
            }
        }

        private static function fileIndex($dir, $field, $value, $id)
        {
            if (strlen($id)) {
                File::mkdir($dir . DS . $field . DS . md5($value), 0777);
                $file = $dir . DS . $field . DS . md5($value) . DS . $id . '.data';
                File::put($file, '');
            }
        }

        public static function makeKey($type, $keyLength = 9)
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook           = Arrays::exists('makeKey', $settings)
            ? $settings['makeKey']
            : null;
            static::_hook($hook, func_get_args(), 'before');

            $dir            = static::checkDir($type);
            $key            = Inflector::quickRandom($keyLength);
            $check          = STORAGE_PATH . DS . 'data' . DS . $dir . DS . 'read' . DS . $key . '.data';

            if (File::exists($check)) {
                static::_hook($hook, func_get_args(), 'after');
                return static::makeKey($type);
            }
            static::_hook($hook, func_get_args(), 'after');
            return $key;
        }

        public static function checkDir($type)
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook           = Arrays::exists('checkDir', $settings)
            ? $settings['checkDir']
            : null;
            static::_hook($hook, func_get_args(), 'before');

            $dirName    = Inflector::lower($type . 's');
            $dir        = STORAGE_PATH . DS . 'data' . DS . $dirName;
            $writeDir   = STORAGE_PATH . DS . 'data' . DS . $dirName . DS . 'write';
            $readDir    = STORAGE_PATH . DS . 'data' . DS . $dirName . DS . 'read';
            $versDir    = STORAGE_PATH . DS . 'data' . DS . $dirName . DS . 'versions';
            $indexDir   = STORAGE_PATH . DS . 'data' . DS . $dirName . DS . 'indexes';
            File::mkdir($dir,       0777);
            File::mkdir($writeDir,  0777);
            File::mkdir($readDir,   0777);
            File::mkdir($versDir,   0777);
            File::mkdir($indexDir,  0777);
            static::_hook($hook, func_get_args(), 'after');
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

        public static function query($type, $conditions = '')
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook           = Arrays::exists('query', $settings)
            ? $settings['query']
            : null;
            static::_hook($hook, func_get_args(), 'before');

            if (!Arrays::exists($type, static::$_db)) {
                static::db($type);
            }


            static::_incQueries(static::_getTime());
            $dirName    = static::checkDir($type);
            $indexDir   = STORAGE_PATH . DS . 'data' . DS . $dirName . DS . 'indexes';
            $queryKey   = sha1(serialize(func_get_args()));

            $cache = static::cache($type, $queryKey);

            if (!empty($cache)) {
                static::_hook($hook, func_get_args(), 'after');
                return $cache;
            }

            $results                = array();
            $resultsAnd             = array();
            $resultsOr              = array();
            $resultsXor             = array();

            $fields                 = ake($type, static::$_fields)      ? static::$_fields[$type]   : static::noConfigFields($type);
            $indexes                = ake('indexes', $settings)         ? $settings["indexes"]      : array();
            $fields['id']           = array();
            $fields['date_create']  = array();
            $datas                  = static::getAll($type);
            if(!count($datas)) {
                static::_hook($hook, func_get_args(), 'after');
                return $results;
            }

            if (!strlen($conditions)) {
                $results        = $datas;
            } else {
                $q = "SELECT * FROM $type WHERE id IS NOT NULL";
                $res = static::$_db[$type]->query($q);
                $next = true;
                while ($row = $res->fetchArray() && true === $next) {
                    $next = false;
                }
                if (true === $next) {
                    foreach ($datas as $tmpObject) {
                        $object = static::getObject($tmpObject, $type);
                        $q = "INSERT INTO $type (id, date_create) VALUES ('" . SQLite3::escapeString($object->id) . "', '" . SQLite3::escapeString($object->date_create) . "')";
                        static::$_db[$type]->exec($q);
                        foreach ($fields as $field => $info) {
                            $value = is_object($object->$field) ? 'object' : $object->$field;
                            $q = "UPDATE $type SET $field = '". SQLite3::escapeString($value) ."' WHERE id = '" . SQLite3::escapeString($object->id) . "'";
                            static::$_db[$type]->exec($q);
                        }
                    }
                }
                list($field, $op, $value) = explode(' ', $conditions, 3);
                $where = "$field $op '" . SQLite3::escapeString($value) . "'";
                $q = "SELECT id FROM $type WHERE $where COLLATE NOCASE";
                $res = static::$_db[$type]->query($q);
                while ($row = $res->fetchArray()) {
                    $object = static::getById($type, $row['id']);
                    array_push($results, $object);
                }
                return $results;

                foreach ($datas as $tmpObject) {
                    $object = static::getObject($tmpObject, $type);

                    $conditions  = repl('NOT LIKE', 'NOTLIKE', $conditions);
                    $conditions  = repl('NOT IN', 'NOTIN', $conditions);

                    list($field, $op, $value) = explode(' ', $conditions, 3);

                    if (Arrays::exists($field, $indexes)) {
                        $indexInfo      = $indexes[$field];
                        $typeIndex      = Arrays::exists('type', $indexInfo) ? $indexInfo['type'] : 'none';
                        if ('fulltext' == $typeIndex) {
                            $words = static::prepareFulltext($value);
                            if (count($words)) {
                                foreach ($words as $word) {
                                    $indexWordDir   = $indexDir . DS . $field . DS . md5($word);
                                    $objects        = glob($indexWordDir . DS . '*.data', GLOB_NOSORT);

                                    if (!$objects) {
                                        $objects = array();
                                    }

                                    if (count($objects)) {
                                        foreach ($objects as $tmpObject) {
                                            $tab = explode(DS, $tmpObject);
                                            $idTmp = repl('.data', '', Arrays::last($tab));
                                            $object = static::getById($type, $idTmp);
                                            array_push($results, $object);
                                        }
                                    }
                                }
                            }
                        } else {
                            $indexDir       .= DS . $field . DS . md5($value);
                            $objects        = glob($indexDir . DS . '*.data', GLOB_NOSORT);

                            if (!$objects) {
                                $objects = array();
                            }

                            if (count($objects)) {
                                foreach ($objects as $tmpObject) {
                                    $tab = explode(DS, $tmpObject);
                                    $idTmp = repl('.data', '', Arrays::last($tab));
                                    $object = static::getById($type, $idTmp);
                                    array_push($results, $object);
                                }
                            }
                        }
                        $cache = static::cache($type, $queryKey, $results);
                        static::_hook($hook, func_get_args(), 'after');
                        return $results;
                    }

                    $continue = true;

                    if (null !== $object->$field) {
                        $continue = static::analyze($object->$field, $op, $value);
                    } else {
                        if ('null' === $value) {
                            $continue = true;
                        } else {
                            $continue = false;
                        }
                    }
                    if (true === $continue) {
                        array_push($results, $object);
                    }
                }
            }
            $cache = static::cache($type, $queryKey, $results);
            static::_hook($hook, func_get_args(), 'after');
            return $results;

            if (!Arrays::is($orderField)) {
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


            if (!strlen($conditions)) {
                $conditionsAnd  = array();
                $conditionsOr   = array();
                $conditionsXor  = array();
                $results        = $datas;
            } else {
                $conditionsOr   = explode(' || ',   $conditions);
                $conditionsAnd  = strstr($conditions, ' && ')
                ? explode(' && ',   $conditions)
                : array();
                $conditionsXor  = strstr($conditions, ' XOR ')
                ? explode(' XOR ',   $conditions)
                : array();
            }

            if (count($conditionsOr) == count($conditionsAnd)) {
                if (Arrays::first($conditionsOr) == Arrays::first($conditionsAnd)) {
                    $conditionsAnd = array();
                }
            }

            if (count($conditionsXor) == count($conditionsOr)) {
                if (Arrays::first($conditionsXor) == Arrays::first($conditionsOr)) {
                    $conditionsXor = array();
                }
            }

            if (count($conditionsAnd)) {
                $thisResults = array();
                $intersectionArray = array();
                foreach ($conditionsAnd as $key => $condition) {
                    $thisResults[$key] = array();
                    foreach ($datas as $tmpObject) {
                        $object = static::getObject($tmpObject, $type);

                        $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
                        $condition  = repl('NOT IN', 'NOTIN', $condition);

                        list($field, $op, $value) = explode(' ', $condition, 3);

                        $continue = true;

                        if (null !== $object->$field) {
                            $continue = static::analyze($object->$field, $op, $value);
                        } else {
                            if ('null' === $value) {
                                $continue = true;
                            } else {
                                $continue = false;
                            }
                        }
                        if (true === $continue) {
                            array_push($thisResults[$key], $tmpObject);
                        }
                    }
                    array_push($intersectionArray, $thisResults[$key]);
                }
                $resultsAnd = call_user_func_array('array_intersect', $intersectionArray);

                if (!count($results)) {
                    $results = $resultsAnd;
                } else {
                    $results = array_intersect($results, $resultsAnd);
                }
                // if (true === $empty) {
                //     $results = array();
                // }
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
                                if ('null' === $value) {
                                $continue = true;
                                } else {
                                    $continue = false;
                                }
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
                            if ('null' === $value) {
                                $continue = true;
                            } else {
                                $continue = false;
                            }
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
                if (Arrays::is($orderField)) {
                    $orderFields = $orderField;
                } else {
                    $orderFields = array($orderField);
                }
                foreach ($orderFields as $orderField) {
                    $sort = array();
                    foreach($results as $object) {
                        $objectCreated = static::getObject($object, $type);
                        foreach ($fields as $k => $infos) {
                            $value = isset($objectCreated->$k) ? $objectCreated->$k : null;
                            $sort[$k][] = $value;
                        }
                    }

                    $asort = array();
                    foreach ($sort as $k => $rows) {
                        for ($i = 0 ; $i < count($rows) ; $i++) {
                            if (empty($$k) || is_string($$k)) {
                                $$k = array();
                            }
                            $asort[$i][$k] = $rows[$i];
                            array_push($$k, $rows[$i]);
                        }
                    }

                    if ('ASC' == Inflector::upper($orderDirection)) {
                        array_multisort($$orderField, SORT_ASC, $asort);
                    } else {
                        array_multisort($$orderField, SORT_DESC, $asort);
                    }
                    $collection = array();
                    foreach ($asort as $k => $row) {
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
            static::_hook($hook, func_get_args(), 'after');

            return $results;
        }

        public static function order($type, $results, $field = 'date_create', $orderDirection = 'ASC')
        {
            $settings   = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook       = Arrays::exists('order', $settings)
            ? $settings['order']
            : null;

            static::_hook($hook, func_get_args(), 'before');
            $queryKey   = sha1(serialize(func_get_args()));
            $cache      = static::cache($type, $queryKey);

            if (null !== $cache) {
                return $cache;
            }
            $fields                 = static::getModel($type);
            $fields['id']           = array();
            $fields['date_create']  = array();
            if (!Arrays::is($field)) {
                if (null !== $field && !Arrays::exists($field, $fields)) {
                    $fields[$field] = array();
                }
            } else {
                foreach ($field as $tmpField) {
                    if (null !== $tmpField && !Arrays::exists($tmpField, $fields)) {
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

            if (Arrays::is($field) && Arrays::is($orderDirection)) {
                if (count($field) == 2) {
                    $first = Arrays::first($field);
                    $second = Arrays::last($field);
                    if ('ASC' == Inflector::upper(Arrays::first($orderDirection)) && 'ASC' == Inflector::upper(end($orderDirection))) {
                        array_multisort($$first, SORT_ASC, $$second, SORT_ASC, $asort);
                    } elseif ('DESC' == Inflector::upper(Arrays::first($orderDirection)) && 'ASC' == Inflector::upper(end($orderDirection))) {
                        array_multisort($$first, SORT_DESC, $$second, SORT_ASC, $asort);
                    } elseif ('DESC' == Inflector::upper(Arrays::first($orderDirection)) && 'DESC' == Inflector::upper(end($orderDirection))) {
                        array_multisort($$first, SORT_DESC, $$second, SORT_DESC, $asort);
                    } elseif ('ASC' == Inflector::upper(Arrays::first($orderDirection)) && 'DESC' == Inflector::upper(end($orderDirection))) {
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
            static::_hook($hook, func_get_args(), 'after');
            return $collection;
        }

        public static function analyze($comp, $op, $value)
        {
            if (isset($comp)) {
                $comp   = Inflector::lower($comp);
                $value  = Inflector::lower($value);
                switch ($op) {
                    case '=':
                        return $comp == $value;
                    case '>=':
                        return $comp >= $value;
                    case '>':
                        return $comp > $value;
                    case '<':
                        return $comp < $value;
                    case '<=':
                        return $comp <= $value;
                    case '<>':
                    case '!=':
                        return $comp <> $value;
                    case 'LIKE':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        return contain($value, $comp);
                    case 'NOTLIKE':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        return !contain($value, $comp);
                    case 'LIKESTART':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        return (substr($comp, 0, strlen($value)) === $value);
                    case 'LIKEEND':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (!strlen($comp)) {
                            return true;
                        }
                        return (substr($comp, -strlen($value)) === $value);
                    case 'IN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        return Arrays::in($comp, $tabValues);
                    case 'NOTIN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        return !Arrays::in($comp, $tabValues);
                }
            }
            return false;
        }

        public static function getModel($type)
        {
            $settings   = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook       = Arrays::exists('getModel', $settings)
            ? $settings['getModel']
            : null;
            static::_hook($hook, func_get_args(), 'before');
            if (Arrays::exists($type, static::$_fields)) {
                static::_hook($hook, func_get_args(), 'after');
                return static::$_fields[$type];
            }
            static::_hook($hook, func_get_args(), 'after');
            return static::noConfigFields($type);
        }

        public static function makeQuery($queryJs, $type)
        {
            $settings   = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook       = Arrays::exists('makeQuery', $settings)
            ? $settings['makeQuery']
            : null;
            static::_hook($hook, func_get_args(), 'before');
            $queryJs = substr($queryJs, 9, -2);

            $query = repl('##', ' && ', $queryJs);
            $query = repl('%%', ' ', $query);
            static::_hook($hook, func_get_args(), 'after');
            return $query;
        }

        public static function makeQueryDisplay($queryJs, $type)
        {
            $fields     = static::getModel($type);

            $queryJs    = substr($queryJs, 9, -2);
            $query      = repl('##', ' AND ', $queryJs);
            $query      = repl('%%', ' ', $query);

            $query      = repl('NOT LIKE', 'ne contient pas', $query);
            $query      = repl('LIKESTART', 'commence par', $query);
            $query      = repl('LIKEEND', 'finit par', $query);
            $query      = repl('LIKE', 'contient', $query);
            $query      = repl('%', '', $query);

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
            $new    = array();
            $new['id'] = $object->id;
            $new['date_create'] = $object->date_create;
            foreach ($fields as $field => $info) {
                if (Arrays::exists($field, $newData)) {
                    $new[$field] = $newData[$field];
                } else {
                    $value = !empty($object->$field) ? $object->$field : null;
                    $new[$field] = $value;
                }
            }
            foreach ($newData as $newField => $value) {
                if (!Arrays::exists($newField, $fields)) {
                    $new[$newField] = $value;
                }
            }
            static::delete($type, $object->id);
            static::store($type, $new, $object->id);
            return static::getById($type, $object->id);
        }

        public static function cache($type, $key, $data = null)
        {
            $settings       = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook           = Arrays::exists('cache', $settings)
            ? $settings['cache']
            : null;
            static::_hook($hook, func_get_args(), 'before');
            $dir    = static::checkDir($type);
            $file   = STORAGE_PATH . DS . 'data' . DS . $dir . DS . $key . '.cache';
            if (File::exists($file)) {
                static::_hook($hook, func_get_args(), 'after');
                return static::unserialize(gzuncompress(File::get($file)));
            }
            if (null !== $data) {
                File::put($file, gzcompress(static::serialize($data), -1));
                static::_hook($hook, func_get_args(), 'after');
                return true;
            }
            static::_hook($hook, func_get_args(), 'after');
            return null;
        }

        public static function event($type)
        {
            $settings   = Arrays::exists($type, static::$_settings)
            ? static::$_settings[$type]
            : static::defaultConfig($type);
            $hook       = Arrays::exists('event', $settings)
            ? $settings['event']
            : null;

            static::_hook($hook, func_get_args(), 'before');
            static::$_all[$type]        = array();
            static::$_indexes[$type]    = array();
            $dir                        = static::checkDir($type);
            $cachedFiles                = glob(STORAGE_PATH . DS . 'data' . DS . $dir . DS . '*.cache', GLOB_NOSORT);

            if (!$cachedFiles) {
                $cachedFiles = array();
            }

            if (count($cachedFiles)) {
                foreach ($cachedFiles as $cachedFile) {
                    $del = File::delete($cachedFile);
                }
            }
            static::_hook($hook, func_get_args(), 'after');
        }

        public static function emptyCache($type)
        {
            return static::event($type);
        }

        public static function __callstatic($method, $args)
        {
            if (substr($method, 0, strlen('add')) == 'add') {
                $type = Inflector::lower(repl('add', '', $method));
                return static::add($type);
            } elseif (substr($method, 0, strlen('new')) == 'new') {
                $type = Inflector::lower(repl('new', '', $method));
                $what = count($args) ? Arrays::first($args) : array();
                return static::newOne($type, $what);
            } elseif (substr($method, 0, strlen('edit')) == 'edit') {
                $type = Inflector::lower(repl('edit', '', $method));
                return static::edit($type, Arrays::first($args));
            } elseif (substr($method, 0, strlen('delete')) == 'delete') {
                $type = Inflector::lower(repl('delete', '', $method));
                return static::delete($type, Arrays::first($args));
            } elseif (substr($method, 0, strlen('find')) == 'find') {
                $type = Inflector::lower(repl('find', '', $method));
                return static::getById($type, Arrays::first($args));
            }
        }

        public static function _getTime()
        {
            $time = microtime();
            $time = explode(' ', $time, 2);
            return (Arrays::last($time) + Arrays::first($time));
        }

        public static function _incQueries($start)
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
                    return static::unserialize(static::load($file));
                }
            }
            if (null === $data) {
                return false;
            }
            File::put($file, static::serialize($data));
        }

        public static function evaluate($function, $params = null)
        {
            if (Arrays::is($params)) {
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

        private static function prepareFulltext($text)
        {
            $text = Inflector::urlize(strip_tags(Blog::parse($text)));
            return explode('-', $text);
        }

        private static function _hook($hook, $args, $when)
        {
            if (null !== $hook) {
                if (Arrays::is($hook)) {
                    if (Arrays::exists($when, $hook)) {
                        if ($hook[$when] instanceof \Closure) {
                            $objClosure = new ThinClosure;
                            $arg = array();
                            if (count($args)) {
                                $params = params($args);
                                $arg = $objClosure->populate($params);
                            }
                            $hook[$when]($arg);
                        }
                    }
                }
            }
        }

        public static function defaultConfig($type)
        {
            $config = array();
            $getter = 'getConfigData' . ucfirst(Inflector::lower($type));
            $containerConfig = container()->$getter();

            $configReturn =  !empty($containerConfig) ? $containerConfig : $config;
            static::$_settings[$type] = $configReturn;
            return $configReturn;
        }

        public static function noConfigFields($type)
        {
            $fields = array();
            $getter = 'getFieldsData' . ucfirst(Inflector::lower($type));
            $containerFields = container()->$getter();
            if (!empty($containerFields)) {
                static::$_fields[$type] = $containerFields;
                return $containerFields;
            }
            $all = static::getAll($type);
            if (count($all)) {
                $obj = static::getIt($type, Arrays::first($all));
                $_fields = $obj->_fields;
                foreach ($_fields as $__field) {
                    if ($__field != 'id' && $__field != 'date_create' && $__field != 'thin_type') {
                        $fields[$__field] = array('canBeNull' => true);
                    }
                }
            }
            static::$_fields[$type] = $fields;
            return $fields;
        }

        public static function load($file)
        {
            return file_get_contents($file);
        }

        public static function serialize($what)
        {
            return serialize($what);
        }

        public static function unserialize($what)
        {
            return 2 < strlen($what) ? unserialize($what) : new Object;
        }

        public static function prepareDbLite($type)
        {
            $db = static::lite($type);
            $fields = Data::$_fields[$type];
            $table = $type . 's';
            $q = "SELECT * FROM sqlite_master WHERE type = 'table' AND name = '$table'";
            $res = $db->query($q);
            if(false === $res->fetchArray()) {
                $q = "CREATE TABLE $table (id VARCHAR PRIMARY KEY, date_create";
                if (count($fields)) {
                    foreach ($fields as $field => $infos) {
                        $q .= ", $field";
                    }
                }
                $q .= ");";
                $db->exec($q);
            }
        }

        public function getKeyLite($type, $keyLength = 9)
        {
            $table  = $type . 's';
            $db     = static::lite($type);
            $key    = Inflector::quickRandom($keyLength);
            $q      = "SELECT id FROM $table WHERE id = '" . $key . "'";
            $res    = $db->query($q);
            if(false === $res->fetchArray()) {
                return $key;
            } else {
                return static::getKeyLite($type, $keyLength);
            }
        }

        public static function lite($type)
        {
            return lite($type . 's');
        }

        public static function row($type, array $data, $extends = array())
        {
            if (Arrays::isAssoc($data)) {
                $obj = o(sha1(serialize($data)));
                if (count($extends)) {
                    foreach ($extends as $name => $instance) {
                        $closure = function ($object) use ($name, $instance) {
                            $idx = $object->is_thin_object;
                            $objects = Utils::get('thinObjects');
                            return $instance->$name($objects[$idx]);
                        };
                        $obj->_closures[$name] = $closure;
                    }
                }
                $settings = Arrays::exists($type, static::$_settings) ? static::$_settings[$type] : array();
                if (count($settings)) {
                    $db = Arrays::exists('db', $settings) ? $settings['db'] : null;
                    if (!empty($db)) {
                        $methods = array('save', 'delete');
                        foreach ($methods as $method) {
                            if (!Arrays::exists($method, $obj->_closures)) {
                                $closure = function () use ($type, $method, $obj, $db) {
                                    $name = $method . Inflector::camelize($db);
                                    return $obj->$name($type);
                                };
                                $obj->_closures[$method] = $closure;
                            }
                        }
                    }
                }
                return $obj->populate($data);
            }
            return null;
        }
    }
