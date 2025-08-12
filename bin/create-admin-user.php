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

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Retrieve database connection details from environment variables
$dbDriver = $_ENV['DB_DRIVER'];
$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_DATABASE'];
$dbUser = $_ENV['DB_USERNAME'];
$dbPass = $_ENV['DB_PASSWORD'];

// User details (replace these with actual user data)
$email = 'admin@example.com'; // Replace with admin email
$newPW = 'admin_password';    // Replace with admin password
$username = 'admin';

// Hash the password
$options = [
    'memory_cost' => 1024 * 128,
    'time_cost'   => 6,
    'threads'     => 4,
];
$hashedPassword = password_hash($newPW, PASSWORD_ARGON2ID, $options);

try {
    // Create PDO instance
    if ($dbDriver == 'mysql') {
        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8";
        $pdo = new PDO($dsn, $dbUser, $dbPass);
    } elseif ($dbDriver == 'pgsql') {
        $dsn = "pgsql:host=$dbHost;dbname=$dbName";
        $pdo = new PDO($dsn, $dbUser, $dbPass);
    } elseif ($dbDriver == 'sqlite') {
        $pdo = new PDO("sqlite:" . __DIR__ . '/../foundry.db');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();

    // Insert user and get ID (RETURNING for pgsql, lastInsertId for others)
    if ($dbDriver === 'pgsql') {
        $sql = "INSERT INTO users (email, password, username, status, verified, resettable, roles_mask, registered, last_login, force_logout, tfa_secret, tfa_enabled, auth_method, backup_codes)
                VALUES (:email, :password, :username, 0, 1, 1, 0, 1, NULL, 0, NULL, false, 'password', NULL)
                RETURNING id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email'    => $email,
            ':password' => $hashedPassword,
            ':username' => $username
        ]);
        $userId = (int)$stmt->fetchColumn();
    } else {
        $sql = "INSERT INTO users (email, password, username, status, verified, resettable, roles_mask, registered, last_login, force_logout, tfa_secret, tfa_enabled, auth_method, backup_codes)
                VALUES (:email, :password, :username, 0, 1, 1, 0, 1, NULL, 0, NULL, false, 'password', NULL)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email'    => $email,
            ':password' => $hashedPassword,
            ':username' => $username
        ]);
        $userId = (int)$pdo->lastInsertId();
    }

    // Prepare contact insert (same sample data, different type)
    $contactSql = "INSERT INTO users_contact
        (user_id, type, title, first_name, middle_name, last_name, org, street1, street2, street3, city, sp, pc, cc, voice, fax, email)
        VALUES
        (:user_id, :type, :title, :first_name, :middle_name, :last_name, :org, :street1, :street2, :street3, :city, :sp, :pc, :cc, :voice, :fax, :cemail)";
    $cstmt = $pdo->prepare($contactSql);

    $sample = [
        'title'       => 'Mr',
        'first_name'  => 'John',
        'middle_name' => null,
        'last_name'   => 'Doe',
        'org'         => 'Example LLC',
        'street1'     => '123 Main St',
        'street2'     => null,
        'street3'     => null,
        'city'        => 'Metropolis',
        'sp'          => 'CA',
        'pc'          => '90210',
        'cc'          => 'US',
        'voice'       => '+1.5555555555',
        'fax'         => null,
        'email'       => $email,
    ];

    foreach (['owner','billing','tech','abuse'] as $type) {
        $cstmt->execute([
            ':user_id'     => $userId,
            ':type'        => $type,
            ':title'       => $sample['title'],
            ':first_name'  => $sample['first_name'],
            ':middle_name' => $sample['middle_name'],
            ':last_name'   => $sample['last_name'],
            ':org'         => $sample['org'],
            ':street1'     => $sample['street1'],
            ':street2'     => $sample['street2'],
            ':street3'     => $sample['street3'],
            ':city'        => $sample['city'],
            ':sp'          => $sample['sp'],
            ':pc'          => $sample['pc'],
            ':cc'          => $sample['cc'],
            ':voice'       => $sample['voice'],
            ':fax'         => $sample['fax'],
            ':cemail'      => $sample['email'],
        ]);
    }

    $pdo->commit();

    echo "Admin user and contacts created successfully." . PHP_EOL;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage());
}