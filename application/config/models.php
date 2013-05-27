<?php
    return array(
        /* Entity */
        'ajf' => array(
            /* DB Config Name in application INI */
            'DB' => 'ajf',
            /* tables */
            'tables' => array(
                /* table */
                'questionnaire' => array(
                    'relationship' => array(
                        'partner' => 'manyToOne',
                        'questionnairetype' => 'manyToOne',
                        'appointmentstatus' => 'manyToOne',
                    ),
                ),
                /* table */
                'product' => array(
                    'relationship' => array(
                        'partner' => 'manyToOne',
                    ),
                ),
                /* table */
                'solution' => array(
                    'relationship' => array(
                        'partner' => 'manyToOne',
                    ),
                ),
                /* table */
                'questionnaireprocess' => array(
                    'relationship' => array(
                        'partner' => 'manyToOne',
                        'questionnaire' => 'manyToOne',
                        'question' => 'manyToOne',
                        'answer' => 'manyToOne',
                    ),
                ),
                /* table */
                'questionnaireprospect' => array(
                    'relationship' => array(
                        'appointment' => 'manyToOne',
                        'consultant' => 'manyToOne'
                    ),
                ),
                /* table */
                'questionnairesent' => array(
                    'relationship' => array(
                        'appointment' => 'manyToOne',
                        'consultant' => 'manyToOne',
                    ),
                ),
                /* table */
                'answer' => array(),
                /* table */
                'questionnairehasquestion' => array(
                    'relationship' => array(
                        'questionnaire' => 'manyToMany',
                        'question' => 'manyToMany',
                    ),
                ),
                /* table */
                'notification' => array(
                    'relationship' => array(
                        'notificationtype' => 'manyToOne',
                        'appointment' => 'manyToOne',
                        'appointmentstatus' => 'manyToOne',
                    ),
                ),
                /* table */
                'notificationtype' => array(
                    'relationship' => array(
                        'notifications' => 'oneToMany'
                    ),
                ),
                /* table */
                'partner' => array(
                    /* relationships */
                    'relationship' => array(
                        'users' => 'oneToMany',
                        'teams' => 'oneToMany',
                        'owner' => 'manyToOne',
                        'partnerfather' => 'manyToOne',
                        'country' => 'manyToOne',
                    )
                ),
                /* table */
                'role' => array(),
                /* table */
                'user' => array(
                    /* relationships */
                    'relationship' => array(
                        'partner' => 'manyToOne',
                    )
                ),
                /* table */
                'consultant' => array(
                    /* relationships */
                    'relationship' => array(
                        'partner' => 'manyToOne'
                    )
                ),
                /* table */
                'manager' => array(
                    /* relationships */
                    'relationship' => array(
                        'partner' => 'manyToOne',
                    )
                ),
                /* table */
                'directeur' => array(
                    /* relationships */
                    'relationship' => array(
                        'partner' => 'manyToOne',
                    )
                ),
                /* table */
                'contact' => array(
                    /* relationships */
                    'relationship' => array(
                        'contactsource' => 'manyToOne',
                        'contactstatus' => 'manyToOne',
                        'owner' => 'manyToOne',
                        'maritalstatus' => 'manyToOne',
                        'workstatus' => 'manyToOne',
                        'workcontract' => 'manyToOne'
                    )
                ),
                /* table */
                'appointment' => array(
                    /* relationships */
                    'relationship' => array(
                        'owner' => 'manyToOne',
                        'appointmentstatus' => 'manyToOne',
                        'contact' => 'manyToOne',
                        'user' => 'manyToOne',
                    )
                ),
                /* table */
                'event' => array(
                    /* relationships */
                    'relationship' => array(
                        'eventtype' => 'manyToOne',
                        'user' => 'manyToOne',
                    )
                ),
                /* table */
                'team' => array(
                    /* relationships */
                    'relationship' => array(
                        'partner' => 'manyToOne',
                        'manager' => 'manyToOne'
                    )
                ),
                /* table */
                "userhasrole" => array(
                    /* relationships */
                    'relationship' => array(
                        'role' => 'manyToMany',
                        'user' => 'manyToMany',
                        'partner' => 'manyToMany'
                    )
                ),
                /* table */
                "teamhasuser" => array(
                    /* relationships */
                    'relationship' => array(
                        'team' => 'manyToMany',
                        'consultant' => 'manyToMany',
                        'partner' => 'manyToMany'
                    )
                )
            )
        )
    );
