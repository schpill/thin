<?php
    namespace Thin;

    use Doctrine\DBAL\DriverManager,
        Doctrine\DBAL\Configuration;

    /**
     * Pdo class
     *
     * @todo Add inline documentation.
     *
     */
    class Pdo
    {
        /**
         * Constructor.
         *
         * @return void
         */
        public function __construct()
        {
        }

        /**
         * @todo Add inline documentation.
         *
         * @param Container $config
         *
         * @return Doctrine\DBAL\Connection
         */
        public function getDriver(Container $config)
        {
            $connObject = new Configuration();
            $config = $config->assoc();

            // We map our config options to Doctrine's naming of them
            $connParamsMap = array(
                'database' => 'dbname',
                'username' => 'user',
                'hostname' => 'host',
                'pass'     => 'password'
            );

            foreach ($connParamsMap as $key => $param) {
                if (isset($config[$key])) {
                    $config[$param] = $config[$key];
                    unset($config[$key]);
                }
            }

            $type = isAke($config, 'type', 'pdo_mysql');

            $config['driver'] = $type;
            unset($config['type']);

            return DriverManager::getConnection($config, $connObject);
        }

    }
