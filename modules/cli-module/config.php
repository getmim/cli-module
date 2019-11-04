<?php

return [
    '__name' => 'cli-module',
    '__version' => '1.1.0',
    '__git' => 'git@github.com:getphun/cli-module.git',
    '__license' => 'MIT',
    '__author' => [
        'name' => 'Iqbal Fauzi',
        'email' => 'iqbalfawz@gmail.com',
        'website' => 'https://iqbalfn.com/'
    ],
    '__files' => [
        'modules/cli-module' => ['install','update','remove']
    ],
    '__dependencies' => [
        'required' => [
            [
                'cli' => NULL
            ],
            [
                'cli-app' => NULL
            ]
        ],
        'optional' => []
    ],
    'autoload' => [
        'classes' => [
            'CliModule\\Controller' => [
                'type' => 'file',
                'base' => 'modules/cli-module/system/Controller.php',
                'children' => 'modules/cli-module/controller'
            ],
            'CliModule\\Library' => [
                'type' => 'file',
                'base' => 'modules/cli-module/library'
            ]
        ]
    ],
    'gates' => [
        'tool-module' => [
            'priority' => 3000,
            'host' => [
                'value' => 'CLI'
            ],
            'path' => [
                'value' => 'module'
            ]
        ]
    ],
    'routes' => [
        'tool-module' => [
            404 => [
                'handler' => 'Cli\\Controller::show404'
            ],
            500 => [
                'handler' => 'Cli\\Controller::show500'
            ],
            'toolModuleInit' => [
                'info' => 'Create new blank module on current directory',
                'path' => [
                    'value' => 'init'
                ],
                'handler' => 'CliModule\\Controller\\Module::init'
            ],
            'toolModuleController' => [
                'info' => 'Create new controller for current module',
                'path' => [
                    'value' => 'controller (:name)',
                    'params' => [
                        'name' => 'slug'
                    ]
                ],
                'handler' => 'CliModule\\Controller\\Module::controller'
            ],
            'toolModuleHelper' => [
                'info' => 'Create new helper for current module',
                'path' => [
                    'value' => 'helper (:name)',
                    'params' => [
                        'name' => 'slug'
                    ]
                ],
                'handler' => 'CliModule\\Controller\\Module::helper'
            ],
            'toolModuleInterface' => [
                'info' => 'Create new interface for current module',
                'path' => [
                    'value' => 'interface (:name)',
                    'params' => [
                        'name' => 'slug'
                    ]
                ],
                'handler' => 'CliModule\\Controller\\Module::iface'
            ],
            'toolModuleLibrary' => [
                'info' => 'Create new library for current module',
                'path' => [
                    'value' => 'library (:name)',
                    'params' => [
                        'name' => 'slug'
                    ]
                ],
                'handler' => 'CliModule\\Controller\\Module::library'
            ],
            'toolModuleModel' => [
                'info' => 'Create new model for current module',
                'path' => [
                    'value' => 'model (:name)',
                    'params' => [
                        'name' => 'slug'
                    ]
                ],
                'handler' => 'CliModule\\Controller\\Module::model'
            ],
            'toolModuleService' => [
                'info' => 'Create new service for current module',
                'path' => [
                    'value' => 'service (:name)',
                    'params' => [
                        'name' => 'slug'
                    ]
                ],
                'handler' => 'CliModule\\Controller\\Module::service'
            ],
            'toolModuleWatch' => [
                'info' => 'Watch module changes and do sync',
                'path' => [
                    'value' => 'watch (:target)',
                    'params' => [
                        'target' => 'rest'
                    ]
                ],
                'handler' => 'CliModule\\Controller\\Syncer::watch'
            ],
            'toolModuleSync' => [
                'info' => 'Sync module to any exists application',
                'path' => [
                    'value' => 'sync (:target)',
                    'params' => [
                        'target' => 'rest'
                    ]
                ],
                'handler' => 'CliModule\\Controller\\Syncer::sync'
            ]
        ]
    ],
    'cli' => [
        'autocomplete' => [
            '!^module (watch|sync)( .*)?!' => [
                'priority' => 4,
                'handler' => [
                    'class' => 'Cli\\Library\\Autocomplete',
                    'method' => 'files'
                ]
            ],
            '!^module( [a-z]*)?$!' => [
                'priority' => 3,
                'handler' => [
                    'class' => 'CliModule\\Library\\Autocomplete',
                    'method' => 'command'
                ]
            ]
        ]
    ]
];