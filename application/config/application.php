<?php
    $application = array();

    $application['production'] = array(
        'db' => array(
            'ajf' => array(
                'adapter' => 'mysql',
                'host' => 'localhost',
                'username' => 'root',
                'password' => 'root',
                'dbname' => 'ajf',
            ),
        ),
    );

    $application['staging'] = array(

    );

    $application['development'] = array(

    );

    return $application;
