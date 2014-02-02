<?php
    /**
     * Cms class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Cms
    {
        public static function dispatch()
        {
            $query      = new Querydata('page');
            $url        = substr($_SERVER['REQUEST_URI'], 1);
            $homeUrl    = null !== static::getOption('home_page_url') ? static::getOption('home_page_url') : 'home';
            $url        = !strlen($url) ? $homeUrl : $url;

            if ('home' == $url) {
                container()->setCmsIsHomePage(true);
            } else {
                container()->setCmsIsHomePage(false);
            }

            $pages      = static::getPages();
            $routes     = array();

            if (count($pages)) {
                foreach ($pages as $pageTmp) {
                    $routes[$pageTmp->getUrl()] = $pageTmp;
                }
            }

            $found = Arrays::exists($url, $routes);

            if (false === $found) {
                $found = static::match($routes);
            }

            if (true === $found) {
                $page           = $routes[$url];
                $displaymode    = Inflector::lower($page->getDisplaymode()->getName());
                $datePub        = $page->getDateIn();
                $dateDepub      = $page->getDateOut();

                if (strlen($datePub)) {
                    list($d, $m, $y)    = explode('-', $datePub, 3);
                    $datePub            = "$y-$m-$d";
                    $datePub            = new Date($datePub);
                }
                if (strlen($dateDepub)) {
                    list($d, $m, $y)    = explode('-', $dateDepub, 3);
                    $dateDepub          = "$y-$m-$d";
                    $dateDepub          = new Date($dateDepub);
                }

                $now = time();

                if ('online' == $displaymode) {
                    container()->setCmsPage($page);
                } elseif ('offline' == $displaymode || 'brouillon' == $displaymode) {
                    container()->setCmsPage(404);
                } else {
                    container()->setCmsPage($displaymode);
                }

                if ($datePub instanceof Date) {
                    $ts = $datePub->getTimestamp();
                    if ($ts > $now) {
                        container()->setCmsPage(404);
                    }
                }

                if ($dateDepub instanceof Date) {
                    $ts = $dateDepub->getTimestamp();
                    if ($ts < $now) {
                        container()->setCmsPage(404);
                    }
                }
            } else {
                container()->setCmsPage(404);
            }
        }

        private static function match($routes)
        {
            $path   = substr($_SERVER['REQUEST_URI'], 1);
            foreach ($routes as $pathComp => $page) {
                $regex  = '#^' . $pathComp . '#i';
                $res    = preg_match($regex, $path, $values);

                if ($res === 0) {
                    continue;
                }

                foreach ($values as $i => $value) {
                    if (!is_int($i) || $i === 0) {
                        unset($values[$i]);
                    }
                }
                container()->setViewParams($values);
                return true;
            }
            return false;
        }

        public static function language()
        {
            $session = session('cms_lng');
            $lng     = $session->getLanguage();
            if (null === $lng) {
                $lng = static::getOption('default_language');
                if (null === $lng) {
                    throw new Exception("You must provide a default_language in option.");
                }
                $session->setLanguage($lng);
            }
            container()->setCmsLanguage($lng);
        }

        public static function run()
        {
            $page = container()->getCmsPage();
            $theme = static::getOption('theme');
            if (null === $theme) {
                throw new Exception("You must provide a theme in option.");
            }
            if (is_int($page)) {
                $file = THEME_PATH . DS . $theme . DS . '404.php';
            } elseif (is_string($page)) {
                $file = THEME_PATH . DS . $theme . DS . $page . '.php';
            } elseif (is_object($page)) {
                $file = THEME_PATH . DS . $theme . DS . 'page.php';
            }
            $view = new View($file);
            $view->render();
            echo $view->showStats();
        }

        public static function getOption($key)
        {
            $query      = new Querydata('option');
            $res        = $query->where("name = $key")->get();
            if (count($res)) {
                $row    = $query->first($res);
                return $row->getValue();
            }
            return null;
        }

        public static function lng($value, $lng = null)
        {
            if (null === $lng) {
                $lng = container()->getCmsLanguage();
                if (null === $lng) {
                    $lng = static::getOption('default_language');
                }
            }

            if (Arrays::isArray($value)) {
                if (Arrays::exists($lng, $value)) {
                    return $value[$lng];
                }
            }
            return '';
        }

        public static function translate($key, $params = array(), $default = null)
        {
            $page       = container()->getCmsPage();
            $idPage     = $page->getId();
            $query      = new Querydata('translation');
            $res        = $query->where("page = $idPage")->whereAnd("key = $key")->get();

            if (count($res)) {
                $row    = $query->first($res);
                $value  = static::lng($row->getValue(), container()->getCmsLanguage());
            } else {
                $value  = $default;
            }

            if (!empty($value) && count($params)) {
                foreach ($params as $k => $v) {
                    $needle = "##$k##";
                    $value  = repl($needle, $v, $value);
                }
            }

            return $value;
        }

        public static function display()
        {
            $page       = container()->getCmsPage();
            $content    = static::sanitize(static::lng($page->getHtml(), container()->getCmsLanguage()));
            $file       = CACHE_PATH . DS . md5($content . $page->getName()) . '.cms';
            File::delete($file);
            File::put($file, $content);
            require $file;
        }

        public static function execSnippet($name, $params = array())
        {
            $page       = container()->getCmsPage();
            $query      = new Querydata('snippet');
            $res        = $query->where("name = $name")->get();
            if (count($res)) {
                $row    = $query->first($res);
                $code   = $row->getCode();

                if (!empty($code) && !empty($params)) {
                    foreach ($params as $k => $v) {
                        $needle = "##$k##";
                        $code = repl($needle, $v, $code);
                    }
                }
                return static::executePHP($code);
            }
            return null;
        }

        public static function executePHP($code, $purePHP = true)
        {
            $page       = container()->getCmsPage();
            if (true === $purePHP) {
                $content    = static::sanitize('{{' . "\n" . $code);
            } else {
                $content    = static::sanitize($code);
            }
            $file       = CACHE_PATH . DS . md5($content . $page->getName()) . '.cms';
            File::delete($file);
            File::put($file, $content);
            ob_start();
            require $file;
            $render = ob_get_contents();
            ob_end_clean();
            File::delete($file);
            return $render;
        }

        public static function sanitize($content)
        {
            $content = repl('<php>', '<?php ', $content);
            $content = repl('<php>=', '<?php echo ', $content);
            $content = repl('</php>', '?>', $content);
            $content = repl('{{=', '<?php echo ', $content);
            $content = repl('{{', '<?php ', $content);
            $content = repl('}}', '?>', $content);
            $content = repl('<?=', '<?php echo ', $content);
            $content = repl('<? ', '<?php ', $content);
            $content = repl('<?[', '<?php [', $content);
            $content = repl('[if]', 'if ', $content);
            $content = repl('[elseif]', 'elseif ', $content);
            $content = repl('[else if]', 'else if ', $content);
            $content = repl('[else]', 'else:', $content);
            $content = repl('[/if]', 'endif;', $content);
            $content = repl('[for]', 'for ', $content);
            $content = repl('[foreach]', 'foreach ', $content);
            $content = repl('[while]', 'while ', $content);
            $content = repl('[switch]', 'switch ', $content);
            $content = repl('[/endfor]', 'endfor;', $content);
            $content = repl('[/endforeach]', 'endforeach;', $content);
            $content = repl('[/endwhile]', 'endwhile;', $content);
            $content = repl('[/endswitch]', 'endswitch;', $content);

            return $content;
        }

        public static function getPages()
        {
            $collection = array();
            $sql        = new Querydata('page');
            $pages      = $sql->all()->order('name')->get();
            if (count($pages)) {
                foreach($pages as $page) {
                    $collection[] = $page;
                }
            }
            return $collection;
        }
    }
