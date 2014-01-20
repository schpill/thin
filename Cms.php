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
            $url        = !strlen($url) ? 'home' : $url;
            if ('home' == $url) {
                container()->setCmsIsHomePage(true);
            } else {
                container()->setCmsIsHomePage(false);
            }
            $res        = $query->where("url = $url")->get();

            if (count($res)) {
                $page           = $query->first($res);
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
                } elseif ('offline' == $displaymode) {
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

        public static function language()
        {
            $session = session('cms_lng');
            $lng = $session->getLanguage();
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
                $row    = Arrays::first($res);
                return $row->getValue();
            }
            return null;
        }

        public static function lng($value, $lng)
        {
            if (Arrays::isArray($value)) {
                if (ake($lng, $value)) {
                    return $value[$lng];
                }
            }
            return $value;
        }

        public static function display()
        {
            $page       = container()->getCmsPage();
            $content    = static::sanitize(static::lng($page->getHtml(), container()->getCmsLanguage()));
            $file       = CACHE_PATH . DS . md5($content . $page->getName()) . '.compiled';
            if (File::exists($file)) {
                File::delete($file);
            }
            File::put($file, $content);
            require $file;
        }

        private static function sanitize($content)
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
    }
