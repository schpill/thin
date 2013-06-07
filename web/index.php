<?php
    // Define path to application directory
    defined('APPLICATION_PATH') || define('APPLICATION_PATH',   realpath(dirname(__FILE__) . '/../application'));
    defined('CACHE_PATH')       || define('CACHE_PATH',         realpath(dirname(__FILE__) . '/../storage/cache'));
    defined('LOGS_PATH')        || define('LOGS_PATH',          realpath(dirname(__FILE__) . '/../storage/logs'));
    defined('TMP_PATH')         || define('TMP_PATH',           realpath(dirname(__FILE__) . '/../storage/tmp'));
    defined('LANGUAGE_PATH')    || define('LANGUAGE_PATH',      realpath(dirname(__FILE__) . '/../storage/language'));

    // Define path to libs directory
    defined('LIBRARIES_PATH')   || define('LIBRARIES_PATH', APPLICATION_PATH . '/../src');

    // Define application environment
    defined('APPLICATION_ENV')  || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

    define('DS', DIRECTORY_SEPARATOR);
    define('PS', PATH_SEPARATOR);

    // Ensure library/ is on include_path
    set_include_path(implode(PS, array(
        LIBRARIES_PATH,
        get_include_path()
    )));

    require_once 'Thin/Loader.php';

    require_once APPLICATION_PATH . DS . 'Bootstrap.php';
    \Thin\Timer::start();

    \Thin\Bootstrap::init();

    /* stats */
    if (null !== u::get("showStats")) {
        \Thin\Timer::stop();
        $executionTime  = \Thin\Timer::get();
        $queries        = (null === u::get('NbQueries')) ? 0 : u::get('NbQueries');
        $valQueries     = ($queries < 2) ? 'requete SQL executee' : 'requetes SQL executees';
        $SQLDuration    = (null === u::get('SQLTotalDuration')) ? 0 : u::get('SQLTotalDuration');
        $execPHP        = $executionTime - $SQLDuration;
        $PCPhp          = round(($execPHP / $executionTime) * 100, 2);
        $PCSQL          = 100 - $PCPhp;
        echo "\n<!--\n\n\tPage generee en $executionTime s.\n\t$queries $valQueries en $SQLDuration s. (" . ($PCSQL) . " %)\n\tExecution PHP $execPHP s. ($PCPhp %)\n\n\tMemoire utilisee : " . convertSize(memory_get_peak_usage()) . "\n\n-->";
    }
