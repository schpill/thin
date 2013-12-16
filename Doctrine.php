<?php
    namespace Thin;

    class Doctrine
    {
        static $_entity;

        public static function em($entity)
        {
            static::$_entity        = $entity;
            $adapter                = static::getConfig('adapterDoctrine');
            $username               = static::getConfig('username');
            $password               = static::getConfig('password');
            $dbName                 = static::getConfig('dbname');
            $host                   = static::getConfig('host');
            $port                   = static::getConfig('port');
            $proxy_namespace        = static::getConfig('proxy_namespace');
            $proxy_dir              = static::getConfig('proxy_dir');
            $metadata_path          = static::getConfig('metadata_path');
            $yaml_dir               = static::getConfig('yaml_dir');

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
            $config     = new \Doctrine\ORM\Configuration();
            $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ZendDataCache);
            $driverImpl = new \Doctrine\ORM\Mapping\Driver\YamlDriver($yaml_dir);
            $config->setMetadataDriverImpl($driverImpl);
            $config->setProxyDir($metadata_path);
            $config->setProxyNamespace($proxy_namespace);
            $config->setQueryCacheImpl(new \Doctrine\Common\Cache\ArrayCache());
            $config->setResultCacheImpl(new \Doctrine\Common\Cache\ArrayCache());
            $em         = \Doctrine\ORM\EntityManager::create($conn, $config);
            return $em;
        }

        private static function getConfig($key)
        {
            return Config::get('application.db.' . static::$_entity . '.' . $key);
        }
    }
