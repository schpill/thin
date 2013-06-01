<?php
    $application = array();

    $application['production'] = array(
        'application' => array(
            'key' => '5@poplmm145d3225_85fvKL0@',
            'mailjet' => array(
                'login' => '739abc7007a2bbbd050cac4852186259',
                'password' => '03c09a6b64c3024789b4466feca526d2',
            ),
        ),
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
