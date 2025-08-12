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

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$dbDriver = $_ENV['DB_DRIVER'] ?? null;
$dbHost = $_ENV['DB_HOST'] ?? null;
$dbName = $_ENV['DB_DATABASE'] ?? null;
$dbUser = $_ENV['DB_USERNAME'] ?? null;
$dbPass = $_ENV['DB_PASSWORD'] ?? null;

try {
    // Connect to database
    if ($dbDriver == 'mysql') {
        $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE DATABASE `$dbName`");
        echo "Created new database '$dbName'\n";
    }
    if ($dbDriver == 'mysql') {
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    } elseif ($dbDriver == 'pgsql') {
        $pdo = new PDO("pgsql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    } elseif ($dbDriver == 'sqlite') {
        $pdo = new PDO("sqlite:" . $dbName);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Import SQL file
    $baseDir = realpath(__DIR__ . '/../database');
    $driver = strtolower($dbDriver);

    switch ($driver) {
        case 'mysql':
            $sqlFile = "$baseDir/MySQL.sql";
            break;
        case 'pgsql':
            $sqlFile = "$baseDir/PostgreSQL.sql";
            break;
        case 'sqlite':
            $sqlFile = "$baseDir/SQLite.sql";
            break;
        default:
            throw new Exception("Unsupported DB_DRIVER: $driver");
    }
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    echo "Imported SQL file '$sqlFile' into database '$dbName'\n";

} catch (PDOException $e) {
    echo $e->getMessage() . PHP_EOL;
}