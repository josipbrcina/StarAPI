<?php

return [
    /**
     * Fixed Star API configuration
     */
    'internalConfiguration' => [
        'projects' => [
            'reservation' => [
                'maxReservationTime' => env('PROJECT_RESERVATION_TIME', 30)
            ]
        ],
        'taskHistoryStatuses' => [
            'assigned' => 'Task assigned to %s',
            'claimed' => 'Task claimed by %s',
            'paused' => 'Task paused because of: "%s"',
            'resumed' => 'Task resumed',
            'qa_ready' => 'Task ready for QA',
            'qa_fail' => 'Task failed QA',
            'qa_success' => 'Task passed QA',
        ],
        'taskComplexityOptions' => [
            1,
            2,
            3,
            4,
            5,
            6,
            7,
            8,
            9,
            10
        ],
        'profile_update_xp_message' => 'Hey, your XP changed by {N}',
        'guestRole' => 'guest',
        'defaultRole' => 'standard',
        'skills' => [
            'PHP',
            'React',
            'Cloud',
            'Node',
            'Planning'
        ],
        'webDomain' => env('WEB_DOMAIN', 'http://the-shop.io:3000/'),
        'employee_monthly_minimum_earning' => [
            'apprentice' => 10000,
            'junior' => 17500,
            'standard' => 30000,
            'senior' => 45000
        ]
    ],

    /**
     * Dynamic configuration that depends on external services
     */
    'externalConfiguration' => [
        'slack' => [
            [
                'resolver' => [
                    'class' => \Vluzrmos\SlackApi\Facades\SlackTeam::class,
                    'method' => 'info'
                ],
                'settingName' => 'teamInfo'
            ]
        ],
    ]

];
