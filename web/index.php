<?php
    // Define path to application directory
    defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));
    defined('CACHE_PATH') || define('CACHE_PATH', realpath(dirname(__FILE__) . '/../storage/cache'));
    defined('LOGS_PATH') || define('LOGS_PATH', realpath(dirname(__FILE__) . '/../storage/logs'));
    defined('TMP_PATH') || define('TMP_PATH', realpath(dirname(__FILE__) . '/../storage/tmp'));

    // Define path to libs directory
    defined('LIBRARIES_PATH') || define('LIBRARIES_PATH', APPLICATION_PATH . '/../src');

    // Define application environment
    defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

    define('DS', DIRECTORY_SEPARATOR);
    define('PS', PATH_SEPARATOR);

    // Ensure library/ is on include_path
    set_include_path(implode(PS, array(
        LIBRARIES_PATH,
        get_include_path()
    )));

    require_once 'Thin/Loader.php';
