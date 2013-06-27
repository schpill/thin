<?php
    /**
     * Cms class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Cms
    {
        public static function getAll()
        {
            $pages = glob(STORAGE_PATH . DS . 'pages' . DS . '*.page');
            return $pages;
        }

        public static function getPage($page)
        {
            return unserialize(fgc($page));
        }

        public static function getById($id)
        {
            $pages = static::getAll();
            foreach ($pages as $tmpPage) {
                $tab = explode(DS, $tmpPage);
                $keyComp = repl('.page', '', end($tab));
                if ($keyComp == $id) {
                    return static::getPage($tmpPage);
                }
            }
            return null;
        }

        public static function getRoutes()
        {
            $routes = array();
            $pages = static::getAll();
            foreach ($pages as $page) {
                $page = static::getPage($page);
                $routes[$page->getId()] = $page->getRoute();
            }
            return $routes;
        }

        public static function add()
        {
            $session = Session::instance('cms');
            $key = static::makeKey();
            $infos = array('id' => $key, 'author' => $session->getUser(), 'dateCreate' => $date, 'slug' => Inflector::slugify($_POST['title'])) + $_POST;
            $newPost = static::store($infos, $key);
            $route = $newPost->getRoute();
            Utils::go(URLSITE . $route);
        }

        public static function edit($id)
        {
            $session = Session::instance('cms');
            $page = static::getById($id);
            static::delete($id);
            $infos = array('id' => $id, 'author' => $session->getUser(), 'dateCreate' => $page->dateCreate, 'slug' => Inflector::slugify($_POST['title'])) + $_POST;
            $newPost = static::store($infos, $id);
            $route = $newPost->getRoute();
            Utils::go(URLSITE . $route);
        }

        public static function store($flatPage, $key = null)
        {
            $page = new Page;
            $page->populate($flatPage);
            $serialize = serialize($page);
            if (is_null($key)) {
                $key = sha1($serialize);
            }

            $file = STORAGE_PATH . DS . 'pages' . DS . $key . '.page';
            File::delete($file);
            File::put($file, $serialize);
            return $page;
        }

        public static function delete($id)
        {
            $pages = static::getAll();
            foreach ($pages as $tmPpage) {
                $tab = explode(DS, $tmPpage);
                $keyComp = repl('.page', '', end($tab));
                if ($keyComp == $id) {
                    return File::delete($tmPpage);
                }
            }
            return false;
        }

        public static function makeKey()
        {
            $key    = Inflector::quickRandom(9);
            $check  = STORAGE_PATH . DS . 'pages' . DS . $key . '.page';
            if (File::exists($check)) {
                return static::makeKey();
            }
            return $key;
        }
    }
