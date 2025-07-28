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

$dbType = $_ENV['DB_DRIVER'];
$host = $_ENV['DB_HOST'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];

$databaseName = $_ENV['DB_DATABASE'];

try {
    // Connect to database
    if ($dbType == 'mysql') {
        $pdo = new PDO("mysql:host=$host", $username, $password);
    } elseif ($dbType == 'postgresql') {
        $pdo = new PDO("pgsql:host=$host", $username, $password);
    } elseif ($dbType == 'sqlite') {
        $pdo = new PDO("sqlite:host=$host");
    }

    // Set PDO attributes
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create new database
    if ($dbType == 'mysql') {
        $pdo->exec("CREATE DATABASE `$databaseName`");
    } elseif ($dbType == 'postgresql') {
        $pdo->exec("CREATE DATABASE $databaseName");
    } elseif ($dbType == 'sqlite') {
        $pdo->exec("CREATE DATABASE $databaseName");
    }
    echo "Created new database '$databaseName'\n";

    if ($dbType == 'mysql') {
        $pdo = new PDO("mysql:host=$host;dbname=$databaseName", $username, $password);
    } elseif ($dbType == 'postgresql') {
        $pdo = new PDO("pgsql:host=$host;dbname=$databaseName", $username, $password);
    } elseif ($dbType == 'sqlite') {
        $pdo = new PDO("sqlite:" . __DIR__ . "/../$databaseName");
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Import SQL file
    $baseDir = realpath(__DIR__ . '/../database');
    $driver = strtolower($dbType);

    switch ($driver) {
        case 'mysql':
            $sqlFile = "$baseDir/MySQL.sql";
            break;
        case 'postgresql':
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
    echo "Imported SQL file '$sqlFile' into database '$databaseName'\n";

} catch (PDOException $e) {
    echo $e->getMessage() . PHP_EOL;
}