<?php

/**
 * database.php — fixes the MariaDB connection to utf8mb4.
 *
 * Why: CLAUDE.md security rules: utf8mb4 everywhere. The host's default is latin1, and
 * Laravel's own connection takes its character set from DB_CHARSET, so one wrong line
 * in .env would mangle names and emoji. Here the charset and collation are fixed;
 * everything else matches Laravel's default mariadb connection.
 *
 * How it connects: Laravel merges this file with its own config/database.php connection
 * by connection, so this whole connection replaces the default one.
 * DatabaseConnectionTest checks it; the doctor (checkpoint 5) checks the database's and
 * tables' own character sets.
 */

declare(strict_types=1);

use Pdo\Mysql;

return [
    'connections' => [
        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'mask_bindings_in_exception_messages' => env('DB_MASK_BINDINGS', false),
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    ],
];
