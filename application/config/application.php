<?php
    $application = array();

    $application['production'] = array(
        'application' => array(
            'defaultModule' => 'www',
            'key' => '5@poplmm145d3225_85fvKL0@',
            'mailjet' => array(
                'login' => 'xxx',
                'password' => 'xxx',
            ),
        ),
        'db' => array(
            'ajf' => array(
                'adapterDoctrine'   => 'pdo_mysql',
                'adapter'           => 'mysql',
                'host'              => 'localhost',
                'username'          => 'root',
                'password'          => 'root',
                'dbname'            => 'ajf',
                'port'              => '3306',
                'metadata_path'     => APPLICATION_PATH . DS . 'doctrine' . DS . 'entities',
                'proxy_dir'         => APPLICATION_PATH . DS . 'doctrine' . DS . 'proxies',
                'yaml_dir'          => APPLICATION_PATH . DS . 'doctrine' . DS . 'schemas',
                'proxy_namespace'   => 'Proxy',
            ),
        ),
        'database' => array(
            'buffer' => 5
        ),
    );

    $application['staging'] = array(

    );

    $application['development'] = array(

    );

    return $application;
