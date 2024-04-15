<?php

return [
    'displays' => [
        'main' => [
            'index' => 1,
            'main_layer' => 'code'
        ],
        'secondary' => [
            'index' => 2,
            'main_layer' => 'browser'
        ]
    ],
    'layers' => [
        'code' => [
            'windows' => [
                'phpstorm' => [
                    'app' => 'PhpStorm'
                ]
            ],
            'display' => 'main'
        ],
        'terminal' => [
            'windows' => [
                'iterm2' => [
                    'app' => 'iTerm2'
                ]
            ],
            'display' => 'main'
        ],
        'database' => [
            'windows' => [
                'sequel-ace' => [
                    'app' => 'Sequel Ace'
                ]
            ],
            'display' => 'main'
        ],
        'git' => [
            'windows' => [
                'fork' => [
                    'app' => 'Fork'
                ]
            ],
            'display' => 'main'
        ],
        'browser' => [
            'windows' => [
                'firefox' => [
                    'app' => 'Firefox'
                ]
            ],
            'display' => ['secondary', 'main']
        ],
        'messaging' => [
            'layout' => 'grid',
            'grid' => [
                'rows' => 1,
                'cols' => 2
            ],
            'windows' => [
                'slack' => [
                    'app' => 'Slack',
                ],
                'mail' => [
                    'app' => 'Mail',
                    'grid' => [
                        'offset-x' => 1
                    ]
                ]
            ],
            'display' => ['secondary', 'main']
        ],
        'notes' => [
            'windows' => [
                'obsidian' => [
                    'app' => 'Obsidian'
                ]
            ],
            'display' => ['secondary', 'main']
        ]
    ]
];
