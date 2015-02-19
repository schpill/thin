<?php
    namespace Thin\Database;

    use Thin\Arrays;
    use Thin\Inflector;

    class Fulltext
    {
        private $dbw, $dbi;

        public function __construct()
        {
            $this->dbw = model('fulltextword');
            $this->dbi = model('fulltextindex');
        }

        public function prepare($text)
        {
            $slugs = explode(' ', Inflector::slug($text, ' '));
            if (count($slugs)) {
                $collection = array();
                foreach ($slugs as $slug) {
                    if (strlen($slug) > 1) {
                        if (!Arrays::in($slug, $collection)) {
                            array_push($collection, $slug);
                        }
                    }
                }
                asort($collection);
            }
            return $collection;
        }

        public function add($table, $index, $words = array())
        {
            $existing = $this->dbi->where('index = ' . $index)->where("table = '" . $table . "'")->execute(true);
            if (0 < $existing->count()) {
                $existing->delete();
            }
            if (count($words)) {
                foreach ($words as $word) {
                    $sqlWord = $this->dbw->firstOrCreate(array('word' => $word))->save();
                    $sqlIndex = $this->dbi->create(
                        array(
                            'word_id' => $sqlWord->id(),
                            'table' => $table,
                            'index' => $index
                        )
                    )->save();
                }
            }
            return $this;
        }

        public function search($word, $table)
        {
            $words = $this->dbw->likeWord($word)->execute();
            if (count($words)) {
                $ids = array();
                foreach ($words as $searchedWord) {
                    array_push($ids, $searchedWord['id']);
                }
                $indexes = $this->dbi->where('word_id IN (' . implode(',', $ids) . ')')->where("table = '$table'")->execute();
                if (count($indexes)) {
                    $ids = array();
                    foreach ($indexes as $searchedIndex) {
                        array_push($ids, $searchedIndex['id']);
                    }
                    $db = model($table);
                    return $db->where($db->pk() . ' IN (' . implode(',', $ids) . ')')->execute(true);
                }
            }
            return new Collection(array());
        }
    }
