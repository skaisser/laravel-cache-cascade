<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration Path
    |--------------------------------------------------------------------------
    |
    | The path where cached configuration files will be stored.
    | Relative to the base path of your application.
    |
    */
    'config_path' => 'config/dynamic',

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure the caching behavior for the cascade system.
    |
    */
    'cache_prefix' => 'cascade:',
    'default_ttl' => 86400, // 24 hours in seconds
    'use_tags' => false,
    'cache_tag' => 'cache-cascade',

    /*
    |--------------------------------------------------------------------------
    | Visitor Isolation
    |--------------------------------------------------------------------------
    |
    | When enabled, cache keys will be unique per visitor to prevent
    | data leakage between users.
    |
    */
    'visitor_isolation' => false,

    /*
    |--------------------------------------------------------------------------
    | Database Integration
    |--------------------------------------------------------------------------
    |
    | Configure database-related features for the cascade system.
    |
    */
    'use_database' => true,
    'auto_seed' => true,
    'model_namespace' => 'App\\Models\\',
    'seeder_namespace' => 'Database\\Seeders\\',

    /*
    |--------------------------------------------------------------------------
    | Fallback Chain
    |--------------------------------------------------------------------------
    |
    | Define the order of fallback layers. The system will try each layer
    | in order until it finds the requested data.
    |
    | Available layers: 'cache', 'file', 'database'
    |
    */
    'fallback_chain' => ['cache', 'file', 'database'],

    /*
    |--------------------------------------------------------------------------
    | File Storage
    |--------------------------------------------------------------------------
    |
    | Settings for file-based storage layer.
    |
    */
    'file_storage' => [
        'enabled' => true,
        'format' => 'php', // Options: 'php', 'json'
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel Integration
    |--------------------------------------------------------------------------
    |
    | Configure how the package integrates with Laravel's built-in commands.
    |
    */
    'clear_on_cache_clear' => true, // Clear cascade cache when running php artisan cache:clear

    /*
    |--------------------------------------------------------------------------
    | Logging & Debugging
    |--------------------------------------------------------------------------
    |
    | Configure logging for cache operations to help with debugging.
    |
    */
    'logging' => [
        'enabled' => env('CACHE_CASCADE_LOG', false),
        'channel' => env('CACHE_CASCADE_LOG_CHANNEL', 'stack'),
        'level' => env('CACHE_CASCADE_LOG_LEVEL', 'debug'),
        'log_hits' => true,  // Log which layer served the data
        'log_misses' => true, // Log cache misses
        'log_writes' => true, // Log write operations
    ],
];