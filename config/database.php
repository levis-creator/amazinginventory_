<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    | Default: sqlite (if not set in .env)
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                // Disable prepared statement emulation for connection poolers (Supabase, etc.)
                // This prevents "prepared statement does not exist" errors
                PDO::ATTR_EMULATE_PREPARES => env('DB_EMULATE_PREPARES', true),
                PDO::ATTR_PERSISTENT => false,
                // Add connection timeout to prevent hanging on DNS failures
                PDO::ATTR_TIMEOUT => env('DB_CONNECTION_TIMEOUT', 5),
            ]) : [],
        ],

        'supabase' => [
            'driver' => 'pgsql',
            'url' => env('SUPABASE_DB_URL'),
            'host' => env('DB_HOST', env('SUPABASE_DB_HOST')),
            'port' => env('DB_PORT', env('SUPABASE_DB_PORT', '5432')),
            'database' => env('DB_DATABASE', env('SUPABASE_DB_DATABASE', 'postgres')),
            'username' => env('DB_USERNAME', env('SUPABASE_DB_USERNAME', 'postgres')),
            'password' => env('DB_PASSWORD', env('SUPABASE_DB_PASSWORD')),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'require'),
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                // Enable prepared statement emulation for Supabase connection pooler
                // This prevents "prepared statement does not exist" errors with poolers
                PDO::ATTR_EMULATE_PREPARES => env('DB_EMULATE_PREPARES', true),
                PDO::ATTR_PERSISTENT => false,
            ]) : [],
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        'system' => (function () {
            $driver = env('SYSTEM_DB_CONNECTION', 'sqlite');
            $config = [
                'driver' => $driver,
                'url' => env('SYSTEM_DB_URL'),
                'database' => env('SYSTEM_DB_DATABASE', database_path('system.sqlite')),
                'prefix' => '',
                'prefix_indexes' => true,
                'foreign_key_constraints' => env('SYSTEM_DB_FOREIGN_KEYS', true),
            ];

            if ($driver === 'sqlite') {
                // SQLite specific configuration
                // Set busy_timeout to 2 seconds to prevent hanging on locked database
                $config['busy_timeout'] = 2000; // 2 seconds in milliseconds
                $config['journal_mode'] = null;
                $config['synchronous'] = null;
                $config['transaction_mode'] = 'DEFERRED';
                // Add timeout option for PDO
                $config['options'] = extension_loaded('pdo_sqlite') ? [
                    PDO::ATTR_TIMEOUT => 2, // 2 second timeout
                ] : [];
            } else {
                // PostgreSQL/MySQL specific configuration
                $config['host'] = env('SYSTEM_DB_HOST', '127.0.0.1');
                $config['port'] = env('SYSTEM_DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
                $config['username'] = env('SYSTEM_DB_USERNAME');
                $config['password'] = env('SYSTEM_DB_PASSWORD');
                $config['charset'] = env('SYSTEM_DB_CHARSET', 'utf8mb4');
                $config['collation'] = env('SYSTEM_DB_COLLATION', 'utf8mb4_unicode_ci');

                if ($driver === 'pgsql') {
                    // PostgreSQL specific
                    $config['search_path'] = 'public';
                    $config['sslmode'] = env('SYSTEM_DB_SSLMODE', 'prefer');
                    $config['options'] = extension_loaded('pdo_pgsql') ? array_filter([
                        PDO::ATTR_EMULATE_PREPARES => env('SYSTEM_DB_EMULATE_PREPARES', true),
                        PDO::ATTR_PERSISTENT => false,
                    ]) : [];
                }
            }

            return $config;
        })(),

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
