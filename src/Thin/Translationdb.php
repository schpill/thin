<?php
    /**
     * Translation db class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Translationdb
    {
        /**
         * The translate key from the translate configuration file.
         *
         * <code>
         * $config = new translateConfig;
         * $config->populate(array('entity' => 'thin', 'table' => 'eav'));
         * $t = new Translationdb('en', $config);
         * $test = $t->get('hello word');
         * </code>
         *
         * @var string
         */

        private $language;
        private $config;

        public function __construct($language = 'en', $config)
        {
            $this->language = $language;
            $this->config   = $config;
        }

        public function get($sentence)
        {
            return (!is_null($item = $this->translate($sentence))) ? $item : value($sentence);
        }

        /**
         * Retrieve an item from the translation driver.
         *
         * @param  string  $sentence
         * @return string
         */
        public function translate($sentence)
        {
            $key = Inflector::slug($sentence);
            $translate = $this->db()->select("entity_name = 'translation_" . $this->language . "' AND table_name = '" . $key . "'", true);
            if (null !== $translate) {
                return unserialize($translate->getData());
            }
            return null;
        }

        public function put($sentence, $translation)
        {
            $key = Inflector::slug($sentence);
            $translate = $this->db()->select("entity_name = 'translation_" . $this->language . "' AND table_name = '" . $key . "'", true);

            if (empty($translate)) {
                $translate = em($this->config->entity, $this->config->table);
            } else {
                $translate->delete();
                $translate = em($this->config->entity, $this->config->table);
            }

            $data = serialize($translation);

            $translate->setDateAdd(date('Y-m-d H:i:s'))->setEntityName('translation_' . $this->language)->setTableName($key)->setTableId(888)->setData($data);
            $newTranslate = $translate->save();
            return $this;
        }

        /**
         * Get a query builder for the database table.
         *
         * @return Thin\Orm
         */
        protected function db()
        {
            return em($this->config->entity, $this->config->table);
        }
    }
