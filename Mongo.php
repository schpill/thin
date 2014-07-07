<?php
    /**
     * Mongo class
     * @author      Gerald Plusquellec
     */

    namespace Thin;
    use Mongo;
    use MongoId;
    class Mongo
    {
        public $_link;
        public $_db;
        public $_coll;
        public $_lastId;
        public $_count = 0;

        public static $operators = array(
            'greater'       => '$gt',
            '>'             => '$gt',
            'greatereq'     => '$gte',
            '>='            => '$gte',
            'less'          => '$lt',
            '<'             => '$lt',
            'lesseq'        => '$lte',
            '<='            => '$lte',
            'noteq'         => '$ne',
            '!='            => '$ne',
            '<>'            => '$ne',
            'in'            => '$in',
            'notin'         => '$nin',
            'all'           => '$all',
            'size'          => '$size',
            'type'          => '$type',
            'exists'        => '$exists',
            'notexists'     => '$exists',
            'elemmatch'     => '$elemMatch',
            'mod'           => '$mod',
            '%'             => '$mod',
            'equals'        => '$$eq',
            'eq'            => '$$eq',
            '=='            => '$$eq',
            'where'         => '$where'
        );

        public function __construct($dsn)
        {
            $this->_link = new Mongo($dsn);
        }

        public function db($db)
        {
            $this->_db = $this->_link->$db;
        }

        public function coll($coll)
        {
            $this->_coll = $this->_db->$coll;
        }

        public function _copyCollection($db, $from, $to, $index = true)
        {
            if (true === $index) {
                $indexes = $this->_db->selectCollection($from)->getIndexInfo();
                foreach ($indexes as $index) {
                    $options = array();
                    if (isset($index["unique"])) {
                        $options["unique"] = $index["unique"];
                    }
                    if (isset($index["name"])) {
                        $options["name"] = $index["name"];
                    }
                    if (isset($index["background"])) {
                        $options["background"] = $index["background"];
                    }
                    if (isset($index["dropDups"])) {
                        $options["dropDups"] = $index["dropDups"];
                    }
                    $this->_db->selectCollection($to)->ensureIndex($index["key"], $options);
                }
            }
            $ret = $this->_db->execute(
                'function (coll, coll2) { return db.getCollection(coll).copyTo(coll2);}',
                array($from, $to)
            );
            return $ret["ok"];
        }

        public function query($query = array(), $coll = null)
        {
            if (null === $coll) {
                $coll = $this->_coll;
            }
            if (!count($query) || !Arrays::is($query)) {
                return $this->all($coll);
            }

            $q = "";
            $i = 0;
            foreach ($query['queries'] as $queryTmp) {
                list($field, $stm, $value) = $queryTmp;
                if (!strstr($stm, 'LIKE')) {
                    if ($stm == '=') {
                        $stm = '==';
                    } else if ($stm == '<>') {
                        $stm = '!=';
                    }
                    $q .= "this.$field $stm '$value'";
                } else {
                    if ($stm == 'LIKE') {
                        $q .= "(this.$field.match('$value'))";
                    }
                    else if ($stm == 'NOT LIKE') {
                        $q .= "(!this.$field.match('$value'))";
                    }
                }

                if (isset($query['operators'][$i])) {
                    $op = $query['operators'][$i];
                    $q .= " $op ";
                }
                $i++;
            }

            $fn = "function() {
                return $q;
            }";
            $this->_count = $coll->count(array('$where' => $fn));
            return $coll->find(array('$where' => $fn));
        }

        function getOne($id, $coll = null)
        {
            if (null === $coll) {
                $coll = $this->_coll;
            }
            return $coll->findone(array('_id' => new MongoId($id)));
        }

        function delOne($id, $safe = true, $coll = null)
        {
            if (null === $coll) {
                $coll = $this->_coll;
            }
            return $coll->remove(array('_id' => new MongoId($id)), array("safe" => $safe));
        }

        function delQuery($query = array(), $coll = null)
        {
            $res = $this->query($query, $coll);
            foreach ($res as $record) {
                $id = $this->getMongoId($record['_id']);
                $this->delOne($id);
            }
        }

        public function delete($criterias, $safe = true, $coll = null)
        {
            if (null === $coll) {
                $coll = $this->_coll;
            }
            return $coll->remove($criterias, array("safe" => $safe));
        }

        public function update($criterias, $data, $coll = null)
        {
            if (null === $coll) {
                $coll = $this->_coll;
            }
            $date['timeUpdate'] = time();
            return $coll->update($criterias, array('$set' => $data), array("multiple" => true));
        }

        public function updateOne($id, $data, $coll = null)
        {
            if (null === $coll) {
                $coll = $this->_coll;
            }
            $date['timeUpdate'] = time();
            return $coll->update(array('_id' => new MongoId($id)), array('$set' => $data));
        }

        public function insert($data, $coll = null)
        {
            if (null === $coll) {
                $coll = $this->_coll;
            }

            $new = new \stdClass;
            $new->dateCreate = time();
            $new->dateUpdate = time();
            foreach ($data as $key => $value) {
                $new->$key = $value;
            }
            $this->_lastId = $coll->insert($new);
        }

        public function all($coll = null)
        {
            if (null === $coll) {
                $coll = $this->_coll;
            }
            $this->_count = $coll->count();
            return $coll->find();
        }

        public function a2o($array = array())
        {
            if (Arrays::is($array)) {
                $data = new \stdClass;
                if (!count($array)) {
                    return $data;
                }

                foreach ($array as $aKey => $aVal) {
                    $data->{$aKey} = $aVal;
                }
                return $data;
            }
            return false;
        }

        public function getMongoId($id)
        {
            $mongo_id = '';
            if (isset($id)) {
                if(is_object($id)) {
                    foreach($id as $key => $value) {
                        if($key == '$id') {
                            $mongo_id = $value;
                        }
                    }
                }
                return (string) $mongo_id;
            }
            else {
                return (string) $id;
            }
        }

        public function arrayed($theseObjs)
        {
            if(is_object($theseObjs)) {
                $objects = array();
                foreach($theseObjs as $thisObj) {
                    $thisObject = array();
                    foreach($thisObj as $key => $value) {
                        $thisObject[$key] = $value;
                    }
                    $objects[] = $thisObject;
                }
                if(Arrays::is($objects)) {
                    if(!empty($objects)) {
                        return $objects;
                    }
                }
            }
        }
    }
