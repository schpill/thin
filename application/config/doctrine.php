<?php
    return array(
        'doctrine2' => array(
            'auto_generate_proxy_classes' => true
        ),

        'ajf' => array(
            'connection'  => array(
                'dsn'               => 'mysql:host=localhost;dbname=ajf',
                'username'          => 'root',
                'password'          => 'root',
            ),
            'profiling'         => true,
            'metadata_path'     => APPLICATION_PATH . DS . 'doctrine' . DS . 'entities',
            'proxy_dir'         => APPLICATION_PATH . DS . 'doctrine' . DS . 'proxy',
            'proxy_namespace'   => 'Proxy',
        ),
    );
