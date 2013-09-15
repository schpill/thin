<?php
    namespace Thin;
    // Define path to application directory
    defined('APPLICATION_PATH') || define('APPLICATION_PATH',   realpath(dirname(__FILE__) . '/../application'));
    defined('CONFIG_PATH')      || define('CONFIG_PATH',        realpath(dirname(__FILE__) . '/../application/config'));
    defined('CACHE_PATH')       || define('CACHE_PATH',         realpath(dirname(__FILE__) . '/../storage/cache'));
    defined('LOGS_PATH')        || define('LOGS_PATH',          realpath(dirname(__FILE__) . '/../storage/logs'));
    defined('TMP_PATH')         || define('TMP_PATH',           realpath(dirname(__FILE__) . '/../storage/tmp'));
    defined('STORAGE_PATH')     || define('STORAGE_PATH',       realpath(dirname(__FILE__) . '/../storage'));
    defined('MUSIC_PATH')       || define('MUSIC_PATH',         realpath(dirname(__FILE__) . '/../storage/music'));
    defined('PHOTOS_PATH')      || define('PHOTOS_PATH',        realpath(dirname(__FILE__) . '/assets/photos'));
    defined('FILES_PATH')       || define('FILES_PATH',         realpath(dirname(__FILE__) . '/assets/files'));

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
    Timer::start();

    Bootstrap::init();

    /* stats */
    if (null !== Utils::get("showStats")) {
        Timer::stop();
        $executionTime      = Timer::get();
        $queries            = (null === Utils::get('NbQueries')) ? 0 : Utils::get('NbQueries');
        $valQueries         = ($queries < 2) ? 'requete SQL executee' : 'requetes SQL executees';
        $SQLDuration        = (null === Utils::get('SQLTotalDuration')) ? 0 : Utils::get('SQLTotalDuration');

        $queriesNoSQL       = (null === Utils::get('NbQueriesNOSQL')) ? 0 : Utils::get('NbQueriesNOSQL');
        $valQueriesNoSQL    = ($queriesNoSQL < 2) ? 'requete NoSQL executee' : 'requetes NoSQL executees';
        $SQLDurationNoSQL   = (null === Utils::get('SQLTotalDurationNOSQL')) ? 0 : Utils::get('SQLTotalDurationNOSQL');

        $execPHPSQL         = $executionTime - $SQLDuration;
        $execPHPNoSQL       = $executionTime - $SQLDurationNoSQL;
        $execPHP            = $executionTime - $SQLDuration;
        $PCPhp              = round(($execPHP / $executionTime) * 100, 2);
        $PCPhpSQL           = round(($execPHPSQL / $executionTime) * 100, 2);
        $PCPhpNoSQL         = round(($execPHPNoSQL / $executionTime) * 100, 2);
        $PCSQL              = 100 - $PCPhpSQL;
        $PCNoSQL            = 100 - $PCPhpNoSQL;
        echo "\n<!--\n\n\tPage generee en $executionTime s.\n\t$queries $valQueries en $SQLDuration s. (" . ($PCSQL) . " %)\n\t$queriesNoSQL $valQueriesNoSQL en $SQLDurationNoSQL s. (" . ($PCNoSQL) . " %)\n\tExecution PHP $execPHP s. ($PCPhp %)\n\n\tMemoire utilisee : " . convertSize(memory_get_peak_usage()) . "\n\n-->";
    }
