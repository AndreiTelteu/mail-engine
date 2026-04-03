<?php

use App\Models\Email;

return [
    'driver' => env('SCOUT_DRIVER', env('APP_ENV') === 'testing' ? 'null' : 'typesense'),

    'prefix' => env('SCOUT_PREFIX', ''),

    'queue' => env('SCOUT_QUEUE', false),

    'after_commit' => env('SCOUT_AFTER_COMMIT', true),

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    'soft_delete' => false,

    'identify' => env('SCOUT_IDENTIFY', false),

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
        'index-settings' => [],
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [],
    ],

    'typesense' => [
        'client-settings' => [
            'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
            'nodes' => [
                [
                    'host' => env('TYPESENSE_HOST', 'localhost'),
                    'port' => env('TYPESENSE_PORT', '8108'),
                    'path' => env('TYPESENSE_PATH', ''),
                    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                ],
            ],
            'nearest_node' => [
                'host' => env('TYPESENSE_HOST', 'localhost'),
                'port' => env('TYPESENSE_PORT', '8108'),
                'path' => env('TYPESENSE_PATH', ''),
                'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
            ],
            'connection_timeout_seconds' => env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
            'healthcheck_interval_seconds' => env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
            'num_retries' => env('TYPESENSE_NUM_RETRIES', 3),
            'retry_interval_seconds' => env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
        ],
        'model-settings' => [
            Email::class => [
                'collection-schema' => [
                    'name' => 'emails',
                    'fields' => [
                        [
                            'name' => 'id',
                            'type' => 'int64',
                        ],
                        [
                            'name' => 'user_id',
                            'type' => 'int64',
                            'facet' => true,
                        ],
                        [
                            'name' => 'from_address',
                            'type' => 'string',
                        ],
                        [
                            'name' => 'from_name',
                            'type' => 'string',
                            'optional' => true,
                        ],
                        [
                            'name' => 'subject',
                            'type' => 'string',
                        ],
                        [
                            'name' => 'body_text',
                            'type' => 'string',
                        ],
                        [
                            'name' => 'folder',
                            'type' => 'string',
                            'facet' => true,
                        ],
                        [
                            'name' => 'date',
                            'type' => 'int64',
                        ],
                    ],
                    'default_sorting_field' => 'date',
                ],
                'search-parameters' => [
                    'query_by' => 'subject,from_address,from_name,body_text,folder',
                ],
            ],
        ],
        'import_action' => env('TYPESENSE_IMPORT_ACTION', 'upsert'),
    ],
];
