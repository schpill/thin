<?php
    /**
     * View class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class View
    {
        public $_viewFile, $_module, $_cache;
        private $_alert;
        protected $_grammar = array();
        public $_compiled = true;
        /**
         * Callback for escaping.
         *
         * @var string
         */
        private $_escape = 'htmlspecialchars';

        /**
         * Encoding to use in escaping mechanisms; defaults to utf-8
         * @var string
         */
        private $_encoding = 'UTF-8';

        /**
         * Stack of View_Filter names to apply as filters.
         * @var array
         */
        private $_filter = array();

        /**
         * Stack of View_Filter objects that have been loaded
         * @var array
         */
        private $_filterClass = array();

        /**
         * Map of filter => class pairs to help in determining filter class from
         * name
         * @var array
         */
        private $_filterLoaded = array();

        /**
         * Map of filter => classfile pairs to aid in determining filter classfile
         * @var array
         */
        private $_filterLoadedDir = array();
        public static $urlsite      = null;

        public function __construct($viewFile = null)
        {
            if (null !== $viewFile) {
                $this->_module   = 'www';
                if (strstr($viewFile, DS)) {
                    $this->_viewFile = $viewFile;
                } else {
                    $file = CACHE_PATH . DS . md5($this->_viewFile . time() . Utils::UUID()) . '.fake';
                    File::create($file);
                    $fp = fopen($file, 'a');
                    fwrite($fp, '<?php echo $this->content; ?>');
                    $this->_viewFile = $file;
                }
            } else {
                $route      = Utils::get('appDispatch');
                $module     = $route->getModule();
                $controller = $route->getController();
                $action     = $route->getAction();

                $this->_module   = $module;
                $isTranslate = Utils::get('isTranslate');
                if (true === $isTranslate) {
                    $lng = getLanguage();
                    if (true === container()->getMultiSite()) {
                        $this->_viewFile   = APPLICATION_PATH . DS . SITE_NAME . DS . 'modules' . DS . $module . DS . 'views' . DS . 'scripts' . DS . Inflector::lower($controller) . DS . Inflector::lower($lng) . DS . Inflector::lower($action) . '.phtml';
                    } else {
                        $this->_viewFile   = APPLICATION_PATH . DS . 'modules' . DS . $module . DS . 'views' . DS . 'scripts' . DS . Inflector::lower($controller) . DS . Inflector::lower($lng) . DS . Inflector::lower($action) . '.phtml';
                    }
                } else {
                    if (true === container()->getMultiSite()) {
                        $this->_viewFile   = APPLICATION_PATH . DS . SITE_NAME . DS . 'modules' . DS . $module . DS . 'views' . DS . 'scripts' . DS . Inflector::lower($controller) . DS . Inflector::lower($action) . '.phtml';
                    } else {
                        $this->_viewFile   = APPLICATION_PATH . DS . 'modules' . DS . $module . DS . 'views' . DS . 'scripts' . DS . Inflector::lower($controller) . DS . Inflector::lower($action) . '.phtml';
                    }
                }
            }

            Utils::set('appView', $this);
            Utils::set('showStats', true);
        }

        public function includes($tpl)
        {
            $tab = explode(DS, $this->_viewFile);
            $path = repl(Arrays::last($tab), $tpl, $this->_viewFile);
            if (File::exists($path)) {die($path);
                include_once $path;
            }
        }

        public function partial($partial, array $params = array(), $cache = false, $echo = true, $module = null)
        {
            if (count($params)) {
                foreach ($params as $k => $v) {
                    $this->$k = $v;
                }
            }

            if (false !== $cache) {
                $this->setCache($cache);
            }

            if (File::exists($partial)) {
                $viewFile = $partial;
            } else {
                $module = (null === $module) ? $this->_module : $module;
                if (true === container()->getMultiSite()) {
                    $viewFile = APPLICATION_PATH . DS . SITE_NAME . DS . 'modules' . DS . $module . DS . 'views' . DS . 'scripts' . DS . $partial;
                } else {
                    $viewFile = APPLICATION_PATH . DS . 'modules' . DS . $module . DS . 'views' . DS . 'scripts' . DS . $partial;
                }
            }

            if (File::exists($viewFile)) {
                $this->render($viewFile, $echo);
            } else {
                throw new Exception("This partial '$viewFile' does not exist.");
            }
        }

        public function partialLoop($partial, array $data, $iterator, $cache = false, $echo = true, $module = null)
        {
            $result = '';

            if (count($data)) {
                foreach ($data as $key => $value) {
                    $with = array('key' => $key, $iterator => $value);
                    $result .= $this->partial($partial, $with, $cache, false, $module);
                }
            }
            if (true === $echo) {
                return $result;
            } else {
                echo $result;
            }
        }

        public function viewRenderer($tpl)
        {
            if (File::exists($tpl)) {
                $this->_viewFile = $tpl;
            }
            return $this;
        }

        public function render($partial = null, $echo = true)
        {
            $file = (null === $partial) ? $this->_viewFile : $partial;
            $this->_viewFile = $file;
            $this->_run($echo);
        }

        public static function display($page)
        {
            $tpl = $page->getTpl();
            if (File::exists($tpl)) {
                $content = fgc($tpl);
                $content = repl('$this->', '$page->', $content);
                $file = CACHE_PATH . DS . sha1($content) . '.display';
                File::put($file, $content);
                ob_start();
                include $file;
                $html = ob_get_contents();
                ob_end_clean();
                File::delete($file);
                return $html;
            }
            return '';
        }

        protected function _run()
        {
            $echo = func_get_arg(0);
            $file = $this->_viewFile;
            $isExpired = $this->expired();
            if (false === $this->_compiled) {
                $isExpired = true;
            }
            if (false === $isExpired) {
                $file = $this->compiled();
            } else {
                if (is_numeric($this->_cache)) {
                    $cacheInst = new Cache(CACHE_PATH . DS);
                    $hash = sha1($this->compiled() . $this->_cache . _serialize((array)$this)) . '.cache';
                    $cacheInst->forget($hash);
                }
                $file = $this->compiled($file);
            }
            if (null !== $this->_cache) {
                $isCached = isCached($file, $this->_cache, (array)$this);
                if (false === $isCached) {
                    ob_start();
                    include $file;
                    $content = ob_get_contents();
                    ob_end_clean();
                    if (true === $echo) {
                        echo cache($file, $content, $this->_cache, (array)$this);
                    } else {
                        return cache($file, $content, $this->_cache, (array)$this);
                    }
                } else {
                    $content = cache($file, null, $this->_cache, (array)$this);
                    if (true === $echo) {
                        echo $content;
                    } else {
                        return $content;
                    }
                }
            } else {
                if (true === $echo) {
                    include $file;
                } else {
                    ob_start();
                    include $file;
                    $content = ob_get_contents();
                    ob_end_clean();
                    $hash = sha1($this->_viewFile);
                    Utils::set($hash, $content);
                    return true;
                }
            }
        }

        protected function expired()
        {
            if (!File::exists($this->compiled()) || !File::exists($this->_viewFile)) {
                return true;
            }
            return File::modified($this->_viewFile) > File::modified($this->compiled());
        }

        protected function compiled($compile = false)
        {
            $file = CACHE_PATH . DS . md5($this->_viewFile) . '.compiled';
            if (false !== $compile) {
                if (File::exists($file)) {
                    File::delete($file);
                }
                File::put($file, $this->makeCompile($compile));
            }
            return $file;
        }

        public function alert($alert)
        {
            $this->_alert = $alert;
        }

        public function asset($file, $echo = true)
        {
            $url = URLSITE . 'assets/themes/' . SITE_NAME . '/' . $file;
            if (true === $echo) {
                echo $url;
            } else {
                return $url;
            }
        }
        public static function cleanCode($content)
        {
            $content = repl('<php>', '<?php ', self::lng($content));
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
            $content = repl('<lang>', '<?php echo __("', $content);
            $content = repl('</lang>', '"); ?>', $content);
            $content = repl('$this->partial(', 'displays(', $content);
            $content = repl('includes(', 'echo $this->partial(', $content);
            return $content;
        }

        protected function makeCompile($file)
        {
            $content = fgc($file);

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
            $content = repl('$this->partial(', 'displays(', $content);
            $content = repl('includes(', 'echo $this->partial(', $content);
            if (count($this->_grammar)) {
                foreach ($this->_grammar as $grammar => $replace) {
                    $content = repl($grammar, $replace, $content);
                }
            }

            return self::lng($content);
        }

        public static function lng($content)
        {
            $tab = explode('<lang args', $content);
            if (count($tab)) {
                array_shift($tab);
                foreach ($tab as $row) {
                    $args        = Utils::cut('="', '"', trim($row));
                    $default    = Utils::cut('="' . $args . '">', '</lang>', trim($row));
                    $content    = repl('<lang args="' . $args . '">' . $default . '</lang>', '<?php echo trad("' . repl('"', '\\"', $args) . '", "' . repl('"', '\\"', $default) . '"); ?>', $content);
                }
            }
            return $content;
        }

        public function setGrammar($grammar, $replace)
        {
            if (!Arrays::exists($grammar, $this->_grammar)) {
                $this->_grammar[$grammar] = $replace;
            }
            return $this;
        }

        public function setCache($duration)
        {
            if (is_numeric($duration)) {
                if (0 < $duration) {
                    $this->_cache = $duration;
                }
            }
            return $this;
        }

        public function __call($func, $argv)
        {
            if (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                if (isset($this->$var)) {
                    return $this->$var;
                } else {
                    return null;
                }
            } elseif (substr($func, 0, 3) == 'set') {
                $value = Arrays::first($argv);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($func, 3)));
                $var = Inflector::lower($uncamelizeMethod);
                $this->$var = $value;
                return $this;
            }
            if (!is_callable($func) || substr($func, 0, 3) !== 'set' || substr($func, 0, 3) !== 'get') {
                throw new \BadMethodCallException(__class__ . ' => ' . $func);
            }
        }

        public function __set($name, $value)
        {
            $this->$name = $value;
            return $this;
        }

        public function __get($name)
        {
            if (isset($this->$name)) {
                return $this->$name;
            }
            return null;
        }

        public function noCompiled()
        {
            $this->_compiled = false;
        }

        public function addAsset($asset, array $configAsset = array(), $ext = null)
        {
            $configs = Utils::get('FTVConfig');

            $versionJs = $configs['app']['js']['version'];
            $versionCss = $configs['app']['css']['version'];

            if(null === $ext) {
                $tabString = explode('.', Inflector::lower($asset));
                $ext = Arrays::last($tabString);
            }
            if ($ext == 'css') {
                $assetHtml = '<link href="' . $asset . '?v=' . $versionCss . '"';
                if (count($configAsset)) {
                    foreach ($configAsset as $key => $value) {
                        $assetHtml .= " $key=\"$value\" ";
                    }
                    $assetHtml = Inflector::substr($assetHtml, 0, -1);
                }
                $assetHtml .= ' />' . "\n";
                return $assetHtml;
            } else if ($ext == 'ico') {
                return '<link rel="shortcut icon" href="' . $asset . '" type="image/x-icon" />' . "\n";
            } else if ($ext == 'js') {
                return '<script type="text/javascript" src="' . $asset . '?v=' . $versionJs . '"></script>' . "\n";
            } elseif (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'bmp'))) {
                $assetHtml = '<img src="' . $asset . '"';
                if (count($configAsset)) {
                    foreach ($configAsset as $key => $value) {
                        $assetHtml .= " $key=\"$value\" ";
                    }
                    $assetHtml = Inflector::substr($assetHtml, 0, -1);
                }
                $assetHtml .= ' />' . "\n";
                return $assetHtml;
            } else {
                return $asset;
            }
        }

        public function setEscape($spec)
        {
            $this->_escape = $spec;
            return $this;
        }

        /**
         * Convert a string from one encoding to another.
         *
         * @param string $string The string to convert
         * @param string $to     The input encoding
         * @param string $from   The output encoding
         *
         * @return string The string with the new encoding
         *
         * @throws \RuntimeException if no suitable encoding function is found (iconv or mbstring)
         */
        public function convertEncoding($string, $to, $from)
        {
            if (function_exists('mb_convert_encoding')) {
                return mb_convert_encoding($string, $to, $from);
            } elseif (function_exists('iconv')) {
                return iconv($from, $to, $string);
            }

            throw new \RuntimeException('No suitable convert encoding function (use UTF-8 as your encoding or install the iconv or mbstring extension).');
        }

        public function escape($var)
        {
            if (in_array($this->_escape, array('htmlspecialchars', 'htmlentities'))) {
                return call_user_func($this->_escape, $var, ENT_COMPAT, $this->_encoding);
            }

            if (1 == func_num_args()) {
                return call_user_func($this->_escape, $var);
            }
            $args = func_get_args();
            return call_user_func_array($this->_escape, $args);
        }

        /**
         * Set encoding to use with htmlentities() and htmlspecialchars()
         *
         * @param string $encoding
         * @return Zend_View_Abstract
         */
        public function setEncoding($encoding)
        {
            $this->_encoding = $encoding;
            return $this;
        }

        /**
         * Return current escape encoding
         *
         * @return string
         */
        public function getEncoding()
        {
            return $this->_encoding;
        }

        public static function utf8($str)
        {
            return Inflector::utf8($str);
        }

        public static function twig($template, $params = array())
        {
            if (!class_exists('Twig_Autoloader')) {
                require_once 'Twig/Autoloader.php';
            }

            $tab    = explode(DS, $template);
            $file   = Arrays::last($tab);

            $path   = repl(DS . $file, '', $template);

            $loader = new \Twig_Loader_Filesystem($path);
            $twig   = new \Twig_Environment(
                $loader,
                array(
                    'cache' => CACHE_PATH
                )
            );

            echo $twig->render($file, $params);
        }

        public static function evaluate($template, array $parameters)
        {
            extract($parameters, EXTR_SKIP);
            if (File::exists($template)) {
                if (empty($view)) {
                    throw new Exception("A view is needed to evaluate.");
                }
                ob_start();
                require $template;

                return ob_get_clean();
            } elseif (is_string($template)) {
                if (empty($view)) {
                    throw new Exception("A view is needed to evaluate.");
                }
                ob_start();
                eval('; ?>' . $template . '<?php ;');
                return ob_get_clean();
            } else {
                throw new Exception("A view is needed to evaluate.");
            }
        }

        public static function showStats()
        {
            Timer::stop();
            $executionTime = Timer::get();

            $queries = (null === Utils::get('NbQueries'))
            ? 0
            : Utils::get('NbQueries');

            $valQueries = ($queries < 2)
            ? 'requete SQL executee'
            : 'requetes SQL executees';

            $SQLDuration = (null === Utils::get('SQLTotalDuration'))
            ? 0
            : Utils::get('SQLTotalDuration');

            $queriesNoSQL = (null === Utils::get('NbQueriesNOSQL'))
            ? 0
            : Utils::get('NbQueriesNOSQL');

            $valQueriesNoSQL = ($queriesNoSQL < 2)
            ? 'requete NoSQL executee'
            : 'requetes NoSQL executees';

            $SQLDurationNoSQL = (null === Utils::get('SQLTotalDurationNOSQL'))
            ? 0
            : Utils::get('SQLTotalDurationNOSQL');

            $execPHPSQL         = $executionTime - $SQLDuration;
            $execPHPNoSQL       = $executionTime - $SQLDurationNoSQL;
            $execPHP            = $executionTime - $SQLDuration;
            $PCPhp              = round(($execPHP      / $executionTime) * 100, 2);
            $PCPhpSQL           = round(($execPHPSQL   / $executionTime) * 100, 2);
            $PCPhpNoSQL         = round(($execPHPNoSQL / $executionTime) * 100, 2);
            $PCSQL              = 100 - $PCPhpSQL;
            $PCNoSQL            = 100 - $PCPhpNoSQL;
            return "\n<!--\n\n\tPage generee en $executionTime s. par Thin Framework (C) www.geraldplusquellec.me 1996 - " . date('Y') . "\n\t$queries $valQueries en $SQLDuration s. (" . ($PCSQL) . " %)\n\t$queriesNoSQL $valQueriesNoSQL en $SQLDurationNoSQL s. (" . ($PCNoSQL) . " %)\n\tExecution PHP $execPHP s. ($PCPhp %)\n\n\tMemoire utilisee : " . convertSize(memory_get_peak_usage()) . "\n\n-->";
        }

        public function urlsite($echo = true)
        {
            if (null === static::$urlsite) {
                $protocol = 'http';
                if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                    $protocol = 'https';
                }

                container()->setProtocol($protocol);

                $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . "/";

                if (strstr($urlSite, '//')) {
                    $urlSite = repl('//', '/', $urlSite);
                    $urlSite = repl($protocol . ':/', $protocol . '://', $urlSite);
                }

                if (Inflector::upper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $tab = explode('\\', $urlSite);
                    $r = '';
                    foreach ($tab as $c => $v) {
                        $r .= $v;
                    }
                    $r = repl('//', '/', $r);
                    $r = repl($protocol . ':/', $protocol . '://', $r);
                    $urlSite = $r;
                }

                static::$urlsite = $urlSite;
            }

            if (false === $echo) {
                return static::$urlsite;
            } else {
                echo static::$urlsite;
                return;
            }
        }

        public function url($echo = true)
        {
            $start = substr($this->urlsite(false), 0, -1);
            $follow = 1 < strlen($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $url = $start . $follow;

            if (false === $echo) {
                return $url;
            } else {
                echo $url;
                return;
            }
        }
    }
