<?php
/**
 * Argora Foundry
 *
 * A modular PHP boilerplate for building SaaS applications, admin panels, and control systems.
 *
 * @package    App
 * @author     Taras Kondratyuk <help@argora.org>
 * @copyright  Copyright (c) 2025 Argora
 * @license    MIT License
 * @link       https://github.com/getargora/foundry
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
if (file_exists(__DIR__.'/.env')) {
    $dotenv->load();
}

return [
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'name' => $_ENV['APP_NAME'] ?? 'Loom',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'domain' => $_ENV['APP_DOMAIN'] ?? 'example.com',
    'root' => $_ENV['APP_ROOT'] ?? '/var/www/panel',
    'timezone' => $_ENV['TIME_ZONE'] ?? 'UTC',
    'default' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'loom',
            'username' => $_ENV['DB_USERNAME'] ?? 'db_username',
            'password' => $_ENV['DB_PASSWORD'] ?? 'db_password',
            'unix_socket' => $_ENV['DB_SOCKET'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $_ENV['DB_DATABASE'] ?? __DIR__.'/database.sqlite',
            'prefix' => '',
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? '5432',
            'database' => $_ENV['DB_DATABASE'] ?? 'loom',
            'username' => $_ENV['DB_USERNAME'] ?? 'db_username',
            'password' => $_ENV['DB_PASSWORD'] ?? 'db_password',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
    ],
    'mail' => [
        'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.mailgun.org',
        'port' => $_ENV['MAIL_PORT'] ?? 587,
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'hello@example.com',
            'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Example',
        ],
        'api_key' => $_ENV['MAIL_API_KEY'] ?? 'test-api-key',
        'api_provider' => $_ENV['MAIL_API_PROVIDER'] ?? 'sendgrid',
    ],
    'payment' => [
        'stripe' => $_ENV['STRIPE_SECRET_KEY'] ?? 'stripe-secret-key',
    ],
];