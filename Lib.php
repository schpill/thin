<?php
    namespace Thin;

    class Lib
    {
        public function __callStatic($f, $a)
        {
            $f = strtolower($f);

            if (!empty($a)) {
                return lib($f, $a);
            }

            return lib($f);
        }
    }
