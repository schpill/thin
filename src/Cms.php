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
            $query      = dm('page');
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

        public static function match($routes)
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
            $query      = dm('option');
            $res        = $query->where("name = $key")->get();
            if (count($res)) {
                $row    = $query->first($res);
                return $row->getValue();
            }
            return null;
        }

        public static function __($value, $lng = null)
        {
            if (null === $lng) {
                $lng = getLanguage();
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

        public static function lng($value, $lng = null)
        {
            if (null === $lng) {
                $lng = container()->getCmsLanguage();
                if (null === $lng) {
                    $lng = static::getOption('default_language');
                }
            }

            if (Arrays::is($value)) {
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
            $query      = dm('translation');
            $res        = $query->where("page = $idPage")->where("key = $key")->get();

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
            $query      = dm('snippet');
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

        public static function actionForm($idForm)
        {
            $data = new postrequest();
            $data->populate($_POST);
            $db = dm('adminformaction');
            $res = $db->where('adminform = ' . $idForm)->get();
            if (count($res)) {
                $actionForm = $db->first($res);
                $action = $actionForm->getAction();
                foreach ($_POST as $k => $v) {
                    $action = repl("##$k##", $v, $action);
                }
                return static::executePHP($action);
            }
        }

        public static function executePHP($code, $purePHP = true)
        {
            $page = container()->getCmsPage();
            $page = empty($page) ? new Page : $page;
            $name = $page->getName();
            if (empty($name)) {
                $page->setName(container()->getModule() . container()->getAction());
            }
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
            $sql        = dm('page');
            $pages      = $sql->all()->order('hierarchy')->get();
            if (count($pages)) {
                foreach($pages as $page) {
                    $collection[] = $page;
                }
            }
            return $collection;
        }

        public static function getParents()
        {
            Data::getAll('page');
            $collection = array();
            $sql        = dm('page');
            $pages      = $sql->all()->order('hierarchy')->get();
            if (count($pages)) {
                foreach($pages as $page) {
                    $parent = $page->getParent();
                    if (empty($parent)) {
                        $collection[] = $page;
                    }
                }
            }
            return $collection;
        }

        public static function getChildren($pageParent)
        {
            Data::getAll('page');
            $collection = array();
            $sql        = new Querydata('page');
            $pages      = $sql->all()->order('hierarchy')->get();
            if (count($pages)) {
                foreach($pages as $page) {
                    $parent = $page->getParent();
                    if ($parent == $pageParent->getId()) {
                        $collection[] = $page;
                    }
                }
            }
            return $collection;
        }

        public static function loadDatas()
        {
            set_time_limit(0);
            $session = session('admin');
            $dirData = STORAGE_PATH . DS . 'data';
            if (!is_dir(STORAGE_PATH)) {
                mkdir(STORAGE_PATH, 0777);
            }
            if (!is_dir($dirData)) {
                mkdir($dirData, 0777);
            }
            $entities = array();
            if (is_dir(APPLICATION_PATH . DS . 'models' . DS . 'Data' . DS . SITE_NAME)) {
                $datas = glob(APPLICATION_PATH . DS . 'models' . DS . 'Data' . DS . SITE_NAME . DS . '*.php');
                if (count($datas)) {
                    foreach ($datas as $model) {
                        $infos                      = include($model);
                        $tab                        = explode(DS, $model);
                        $entity                     = repl('.php', '', Inflector::lower(Arrays::last($tab)));
                        $entities[]                 = $entity;
                        $fields                     = $infos['fields'];
                        $settings                   = $infos['settings'];
                        Data::$_fields[$entity]     = $fields;
                        Data::$_settings[$entity]   = $settings;
                    }
                }
            } else {
                mkdir(APPLICATION_PATH . DS . 'models' . DS . 'Data' . DS . SITE_NAME, 0777);
            }

            $customtypes = Data::getAll('customtype');
            if (count($customtypes)) {
                foreach ($customtypes as $path) {
                    $customtype                 = Data::getIt('customtype', $path);
                    $entity                     = 'custom_' . Inflector::lower($customtype->getEntity());
                    $entities[]                 = $entity;
                    Data::$_fields[$entity]     = eval('return ' . $customtype->getChamp() . ';');
                    Data::$_settings[$entity]   = eval('return ' . $customtype->getParam() . ';');
                }
            }
            if (count(Data::$_fields)) {
                foreach (Data::$_fields as $entity => $info) {
                    if (!Arrays::in($entity, $entities)) {
                        $entities[] = $entity;
                    }
                }
            }

            container()->setEntities($entities);
            $adminrights = Data::getAll('adminright');
            if(!count($adminrights)) {
                if (count($entities)) {
                    static::fixtures();
                }
            } else {
                $adminTables = Data::getAll('admintable');
                if (count($adminTables)) {
                    foreach ($adminTables as $path) {
                        $adminTable = Data::getIt('admintable', $path);
                        if ($adminTable instanceof Container) {
                            if (!Arrays::in($adminTable->getName(), $entities)) {
                                $sql = dm('adminright');
                                $rights = $sql->where('admintable = ' . $adminTable->getId())->get();
                                $adminTable->delete();
                                if (count($rights)) {
                                    foreach($rights as $right) {
                                        $right->delete();
                                    }
                                }
                                $session->setRights(array());
                            }
                        }
                    }
                }
            }
        }

        public static function acl()
        {
            if (count(request()->getThinUri()) == 2 && contain('?', $_SERVER['REQUEST_URI'])) {
                list($dummy, $uri) = explode('?', Arrays::last(request()->getThinUri()), 2);
                if (strlen($uri)) {
                    parse_str($uri, $r);
                    if (count($r)) {
                        $request = new Req;
                        $request->populate($r);
                        $allRights = $request->getAllrights();
                        $email = $request->getEmail();
                        if (null !== $email && null !== $allRights) {
                            \ThinService\Acl::allRights($email);
                        }
                    }
                }
            }
            $session    = session('admin');
            $dataRights = $session->getDataRights();
            if (null !== $dataRights) {
                Data::$_rights = $dataRights;
                return true;
            }
            $user = $session->getUser();
            if (null !== $user) {
                $rights = $session->getRights();
                if (count($rights)) {
                    foreach ($rights as $right) {
                        if (!ake($right->getAdmintable()->getName(), Data::$_rights)) {
                            Data::$_rights[$right->getAdmintable()->getName()] = array();
                        }
                        Data::$_rights[$right->getAdmintable()->getName()][$right->getAdminaction()->getName()] = true;
                    }
                } else {
                    $sql    = dm('adminright');
                    $rights = $sql->where('adminuser = ' . $user->getId())->get();
                    if (count($rights)) {
                        foreach ($rights as $right) {
                            if (!ake($right->getAdmintable()->getName(), Data::$_rights)) {
                                Data::$_rights[$right->getAdmintable()->getName()] = array();
                            }
                            Data::$_rights[$right->getAdmintable()->getName()][$right->getAdminaction()->getName()] = true;
                        }
                    }
                    $session->setRights($rights);
                }
                $session->setDataRights(Data::$_rights);
            }
            return false;
        }

        public static function fixtures()
        {
            $adminTables        = Data::getAll('admintable');
            $adminUsers         = Data::getAll('adminuser');
            $adminactions       = Data::getAll('adminaction');
            $adminRights        = Data::getAll('adminright');
            $adminTaskStatus    = Data::getAll('admintaskstatus');
            $adminTaskType      = Data::getAll('admintasktype');
            $adminTaskTypes     = Data::getAll('admintasktype');
            $adminCountries     = Data::getAll('admincountry');
            $options            = Data::getAll('option');
            $bools              = Data::getAll('bool');
            $formTypes          = Data::getAll('adminformfieldtype');

            if (!count($bools)) {
                $bool1 = array(
                    'name'  => 'Oui',
                    'value' => 'true',
                );
                $bool2 = array(
                    'name'  => 'Non',
                    'value' => 'false',
                );

                Data::add('bool', $bool1);
                Data::getAll('bool');
                Data::add('bool', $bool2);
                Data::getAll('bool');
            }

            if (!count($formTypes)) {
                $typesToAdd = array();
                $typesToAdd[] = array(
                    'name'  => 'Classique',
                    'value' => 'text',
                );
                $typesToAdd[] = array(
                    'name'  => 'Texte',
                    'value' => 'textarea',
                );
                $typesToAdd[] = array(
                    'name'  => 'Date',
                    'value' => 'date',
                );
                $typesToAdd[] = array(
                    'name'  => 'Courriel',
                    'value' => 'email',
                );
                $typesToAdd[] = array(
                    'name'  => 'Sélection',
                    'value' => 'select',
                );
                $typesToAdd[] = array(
                    'name'  => 'Téléphone',
                    'value' => 'telephone',
                );
                $typesToAdd[] = array(
                    'name'  => 'Caché',
                    'value' => 'hidden',
                );
                $typesToAdd[] = array(
                    'name'  => 'Url',
                    'value' => 'url',
                );

                foreach ($typesToAdd as $typeToAdd) {
                    Data::add('adminformfieldtype', $typeToAdd);
                    Data::getAll('adminformfieldtype');
                }
            }

            if (!count($adminCountries)) {
                $list = fgc("http://web.gpweb.co/u/45880241/cdn/pays.csv");
                $rows = explode("\n", $list);
                foreach ($rows as $row) {
                    $row = repl('"', '', trim($row));
                    if (contain(';', $row)) {
                        list($id, $name, $upp, $low, $code) = explode(';', $row, 5);

                        $country = array(
                            'name' => $name,
                            'code' => $code
                        );
                        Data::add('admincountry', $country);
                        Data::getAll('admincountry');
                    }
                }
            }

            if (!count($adminTaskTypes)) {
                $types = array(
                    'Bogue',
                    'Snippet',
                    'SEO',
                    'Traduction',
                    'Graphisme',
                    'Contenu',
                    'Html',
                    'Css',
                    'Javascript',
                );
                foreach ($types as $type) {
                    $taskType = array(
                        'name' => $type
                    );
                    Data::add('admintasktype', $taskType);
                    Data::getAll('admintasktype');
                }
            }

            if (!count($adminTaskStatus)) {
                $allStatus = array(
                    1 => 'Attribuée',
                    4 => 'Terminée',
                    2 => 'En cours',
                    7 => 'En suspens',
                    6 => 'En attente d\'information',
                    3 => 'En test',
                    5 => 'Réattribuée',
                    8 => 'Annulée',
                );
                foreach ($allStatus as $priority => $status) {
                    $taskStatus = array(
                        'name' => $status,
                        'priority' => $priority
                    );
                    Data::add('admintaskstatus', $taskStatus);
                    Data::getAll('admintaskstatus');
                }
            }

            if (!count($adminTables)) {
                $entities = container()->getEntities();
                if (count($entities)) {
                    foreach ($entities as $entity) {
                        $table = array(
                            'name' => $entity
                        );
                        Data::add('admintable', $table);
                        Data::getAll('admintable');
                    }
                }
            }

            if (!count($adminactions)) {
                $actions = array(
                    'list',
                    'add',
                    'duplicate',
                    'view',
                    'delete',
                    'edit',
                    'import',
                    'export',
                    'search',
                    'empty_cache'
                );

                foreach ($actions as $action) {
                    $newAction = array(
                        'name' => $action
                    );
                    Data::add('adminaction', $newAction);
                    Data::getAll('adminaction');
                }
            }

            if (!count($adminUsers)) {
                $user = array(
                    'name'      => 'Admin',
                    'firstname' => 'Dear',
                    'login'     => 'admin',
                    'password'  => 'admin',
                    'email'     => 'admin@admin.com',
                );

                Data::add('adminuser', $user);
                Data::getAll('adminuser');
            }

            if (!count($adminRights)) {
                $sql        = dm('adminuser');
                $res        = $sql->where('email = admin@admin.com')->get();
                $user       = $sql->first($res);

                $tables     = Data::getAll('admintable');
                $actions    = Data::getAll('adminaction');

                if (count($tables)) {
                    foreach ($tables as $table) {
                        $table = Data::getIt('admintable', $table);
                        foreach ($actions as $action) {
                            $action = Data::getIt('adminaction', $action);
                            $right = array(
                                'adminuser'     => $user->getId(),
                                'admintable'    => $table->getId(),
                                'adminaction'   => $action->getId()
                            );
                            Data::add('adminright', $right);
                            Data::getAll('adminright');
                        }
                    }
                }
            }

            if (!count($options)) {
                $optionsToAdd = array();

                $optionsToAdd[] = array(
                    'name'  => 'default_language',
                    'value' => 'fr',
                );
                $optionsToAdd[] = array(
                    'name'  => 'lng_fr_display',
                    'value' => 'flag',
                );
                $optionsToAdd[] = array(
                    'name'  => 'page_languages',
                    'value' => 'fr',
                );
                $optionsToAdd[] = array(
                    'name'  => 'theme',
                    'value' => SITE_NAME,
                );
                $optionsToAdd[] = array(
                    'name'  => 'menu_fixed',
                    'value' => 'true',
                );
                $optionsToAdd[] = array(
                    'name'  => 'show_menu',
                    'value' => 'true',
                );
                $optionsToAdd[] = array(
                    'name'  => 'logo_max_width',
                    'value' => 400,
                );
                $optionsToAdd[] = array(
                    'name'  => 'menu_background_color',
                    'value' => '#eee',
                );
                $optionsToAdd[] = array(
                    'name'  => 'menu_link_background_color',
                    'value' => '#444',
                );
                $optionsToAdd[] = array(
                    'name'  => 'menu_link_color',
                    'value' => '#fff',
                );
                $optionsToAdd[] = array(
                    'name'  => 'page_background',
                    'value' => '#e2e2e2',
                );
                $optionsToAdd[] = array(
                    'name'  => 'page_color',
                    'value' => '#333',
                );
                $optionsToAdd[] = array(
                    'name'  => 'show_logo',
                    'value' => 'true',
                );
                $optionsToAdd[] = array(
                    'name'  => 'site_font_family',
                    'value' => 'Oswald',
                );
                $optionsToAdd[] = array(
                    'name'  => 'site_font_size',
                    'value' => '14px',
                );
                $optionsToAdd[] = array(
                    'name'  => 'slideshow_background',
                    'value' => '#222',
                );
                $optionsToAdd[] = array(
                    'name'  => 'slideshow_height',
                    'value' => '500px',
                );
                $optionsToAdd[] = array(
                    'name'  => 'slideshow_text_color',
                    'value' => '#fff',
                );
                $optionsToAdd[] = array(
                    'name'  => 'title_font_family',
                    'value' => 'Passion One',
                );
                $optionsToAdd[] = array(
                    'name'  => 'company_name',
                    'value' => 'My website',
                );
                $optionsToAdd[] = array(
                    'name'  => 'google_fonts',
                    'value' => 'Ubuntu,Oswald,Passion One',
                );
                $optionsToAdd[] = array(
                    'name'  => 'map_marker_name',
                    'value' => 'My website',
                );
                $optionsToAdd[] = array(
                    'name'  => 'map_text',
                    'value' => 'Bienvenue!',
                );
                $optionsToAdd[] = array(
                    'name'  => 'custom_css',
                    'value' => ' ',
                );
                $optionsToAdd[] = array(
                    'name'  => 'latitude',
                    'value' => 48,
                );
                $optionsToAdd[] = array(
                    'name'  => 'longitude',
                    'value' => -71,
                );
                $optionsToAdd[] = array(
                    'name'  => 'contact_email',
                    'value' => 'admin@admin.com',
                );
                $optionsToAdd[] = array(
                    'name'  => 'form_email',
                    'value' => 'admin@admin.com',
                );

                foreach ($optionsToAdd as $optionToAdd) {
                    Data::add('option', $optionToAdd);
                    Data::getAll('option');
                }

                $status1 = array(
                    'name' => 'online',
                );
                $status2 = array(
                    'name' => 'offline',
                );
                $status3 = array(
                    'name' => 'maintenance',
                );
                Data::add('statuspage', $status1);
                Data::getAll('statuspage');
                Data::add('statuspage', $status2);
                Data::getAll('statuspage');
                Data::add('statuspage', $status3);
                Data::getAll('statuspage');

                $sql        = dm('statuspage');
                $res        = $sql->where('name = online')->get();
                $status     = $sql->first($res);

                $page = array(
                    'name'      => 'Accueil',
                    'url'       => array('fr' => 'home'),
                    'template'  => 'home',
                    'parent'    => null,
                    'statuspage'=> $status->getId(),
                    'date_out'  => null,
                    'hierarchy' => 1,
                    'is_home'   => getBool('true')->getId()
                );
                Data::add('page', $page);
                Data::getAll('page');
            }
        }

        public static function makeForm($name)
        {
            $html = '';
            $db = dm('adminform');
            $db2 = dm('adminformfield');
            $res = $db->where('name = ' . $name)->get();
            if (count($res)) {
                $form = $db->first($res);
                $html .= '<form class="form-horizontal" action="' . URLSITE . static::__($form->getPage()->getUrl()) . '" id="form_' . Inflector::lower($name) . '" method="post">';
                $html .= Form::hidden('cms_form', $form->getId(), array('id' => 'cms_form'));

                $fields = $db2->where('adminform = ' . $form->getId())->order('hierarchy')->get();
                if (count($fields)) {
                    foreach ($fields as $field) {
                        $true       = getBool('true');
                        $fieldName  = $field->getName();
                        $label      = static::__($field->getLabel());
                        $type       = $field->getAdminformfieldtype()->getValue();
                        $value      = static::__($field->getDefault());
                        $required   = $field->getRequired() == $true->getId();
                        if (empty($value)) {
                            $value = null;
                        }
                        switch ($type) {
                            case 'hidden':
                                $html .= Form::hidden(
                                    $fieldName,
                                    $value,
                                    array(
                                        'id' => $fieldName
                                    )
                                );
                                break;
                            case 'select':
                                $options = explode("\n", $value);
                                $tab = array();
                                foreach ($options as $row) {
                                    list($key, $val) = explode('##', $row, 2);
                                    $tab[$key] = $val;
                                }
                                $html .= Form::select(
                                    $fieldName,
                                    $tab,
                                    null,
                                    array(
                                        'id' => $fieldName,
                                        'required' => $required
                                    ),
                                    $label
                                );
                                break;
                            case 'password':
                                $html .= Form::password(
                                    $fieldName,
                                    array(
                                        'id' => $fieldName,
                                        'required' => $required
                                    ),
                                    $label
                                );
                                break;
                            default:
                                $html .= Form::$type(
                                    $fieldName,
                                    $value,
                                    array(
                                        'id' => $fieldName,
                                        'required' => $required
                                    ),
                                    $label
                                );
                        }
                    }
                }
                $html .= '<div class="control-group" id="control-group-submit"><label for="submit" class="control-label labelrequired">&nbsp;</label><div class="control-group" id="control-group-submit" style="position: relative; left: 20px;">' . Form::submit(static::__($form->getButton())) . '</div></div>';
            }
            return $html;
        }
    }
