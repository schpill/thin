<?php
    namespace Thin;

    class Doctrine
    {
        static $_entity;

        public static function em()
        {
            $adapter                = static::getConfig('driver');
            $username               = static::getConfig('username');
            $password               = static::getConfig('password');
            $dbName                 = static::getConfig('dbname');
            $host                   = static::getConfig('host');
            $port                   = static::getConfig('port');
            $proxy_namespace        = static::getConfig('proxy_namespace');
            $proxy_dir              = static::getConfig('proxy_dir');
            $metadata_path          = static::getConfig('metadata_path');
            $config_dir             = static::getConfig('config_dir');

            $config = new \Doctrine\DBAL\Configuration();
            $connectionParams = array(
                'host'      => $host,
                'port'      => $port,
                'user'      => $username,
                'password'  => $password,
                'dbname'    => $dbName,
                'driver'    => $adapter
            );

            $conn       = \Doctrine\DBAL\DriverManager::getConnection($connectionParams);
            $paths = array(APPLICATION_PATH . DS . 'config' . DS . 'doctrine');
            $config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration($paths, APPLICATION_ENV == 'develoment');
            $em = \Doctrine\ORM\EntityManager::create($conn, $config);
            return $em;
        }

        private static function getConfig($key)
        {
            $params = Bootstrap::$bag['config']->getDatabase();
            return isset($params->$key) ? $params->$key : null;
        }
    }
