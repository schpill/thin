<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2015 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    class Moo
    {
        public static function __callStatic($method, $args)
        {
            $db = Inflector::uncamelize($method);

            if (fnmatch('*_*', $db)) {
                list($database, $table) = explode('_', $db, 2);
            } else {
                $database   = SITE_NAME;
                $table      = $db;
            }

            if (!count($args)) {
                return Moo\Db::instance($database, $table);
            } elseif (count($args) == 1) {
                $id = Arrays::first($args);

                if (is_numeric($id)) {
                    return Moo\Db::instance($database, $table)->find($id);
                }
            }
        }
    }
