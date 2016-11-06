<?php

namespace Application;

$imageDir = __DIR__ . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR;

return [
    'db' => [
        'params' => [
            'host'     => 'localhost',
            'username' => 'autowp_test',
            'password' => 'test',
            'dbname'   => 'autowp_test',
        ],
        'defaultMetadataCache' => null,
    ],
    'users' => [
        'salt'      => 'users-salt',
        'emailSalt' => 'email-salt'
    ],
    'mail' => [
        'transport' => [
            'type' => 'in-memory'
        ],
    ],
    'cachemanager' => [
        'fast' => [
            /*'caching' => false,
            'write_control' => false,*/
            'frontend' => [
                'name' => 'Core',
                'customFrontendNaming' => 0,
                'options' => [
                    'lifetime' => 1800,
                    'automatic_serialization' => true
                ]
            ],
            'backend' => [
                'name' => 'black-hole'
            ]
        ]
    ],
    'caches' => [
        'fastCache' => [
            'adapter' => [
                'name'     =>'blackHole',
                'lifetime' => 180,
            ],
        ],
        'longCache' => [
            'adapter' => [
                'name'     =>'blackHole',
                'lifetime' => 600
            ],
        ],
        'localeCache' => [
            'adapter' => [
                'name'     =>'blackHole',
                'lifetime' => 600
            ],
        ],
    ],
    'imageStorage' => [
        'dirs' => [
            'format' => [
                'path' => $imageDir . "format",
            ],
            'museum' => [
                'path' => $imageDir . "museum",
            ],
            'user' => [
                'path' => $imageDir . "user",
            ],
            'brand' => [
                'path' => $imageDir . "brand",
            ],
            'picture' => [
                'path' => __DIR__ . '/pictures/',
            ]
        ],
    ]
];