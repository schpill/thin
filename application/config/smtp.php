<?php
    return array(
        'config'                => array(
            'debugMode'         => false,
            'default'           => 'mailjet',
            'connections'       => array(
                'mailjet'       => array(
                    'host'      => 'in.mailjet.com',
                    'port'      => 587,
                    'secure'    => 'tls',
                    'auth'      => true,
                    'user'      => config('application', 'application.mailjet.login'),
                    'password'  => config('application', 'application.mailjet.password'),
                ),
            ),
            'localhost'         => 'thinframework.com',
        )
    );
