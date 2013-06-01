<?php
    $application = array();

    $application['production'] = array(
        'application' => array(
            'key' => '5@poplmm145d3225_85fvKL0@',
            'mailjet' => array(
                'login' => 'xxx',
                'password' => 'xxx',
            ),
        ),
        'db' => array(
            'ajf' => array(
                'adapter' => 'mysql',
                'host' => 'localhost',
                'username' => 'root',
                'password' => 'root',
                'dbname' => 'thin',
            ),
        ),
    );

    $application['staging'] = array(

    );

    $application['development'] = array(

    );

    return $application;
