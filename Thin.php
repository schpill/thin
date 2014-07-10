<?php
    namespace Thin;

    class Thin extends Context
    {
        public function getClass()
        {
            return Inflector::uncamelize(repl('Thin\\', '', get_called_class()));
        }
    }
