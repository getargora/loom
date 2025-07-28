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

use Pinga\Db\PdoDatabase;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$dbType = $_ENV['DB_DRIVER'];
$host = $_ENV['DB_HOST'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];

$databaseName = $_ENV['DB_DATABASE'];

// Get the table name from the user input
$tableName = readline('Enter table name: ');

// Connect to the database using the PDO driver
$pdo = new PDO("mysql:host=$host;dbname=$databaseName;charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$db = Pinga\Db\PdoDatabase::fromPdo($pdo);

// Get the column names and types for the specified table
$columnData = $db->select('DESCRIBE ' . $tableName);

// Create the class name based on the table name (e.g. "users" -> "User")
$className = ucwords($tableName, '_');

// Prepare dynamic parts
$createParams = implode(', ', array_map(fn($col) => '$' . $col['Field'], $columnData));
$quotedAssignments = implode("\n        ", array_map(fn($col) =>
    '$' . $col['Field'] . ' = $this->db->quote($' . $col['Field'] . ');', $columnData));
$insertFields = implode(', ', array_map(fn($col) => $col['Field'], $columnData));
$insertValues = implode(', ', array_map(fn($col) => '$' . $col['Field'], $columnData));
$updateParams = implode(', ', array_map(fn($col) => $col['Field'] . ' = $' . $col['Field'], $columnData));
$updateArrayParams = implode(', ', array_map(fn($col) => '$' . $col['Field'], $columnData));
$updateFunctionParams = implode(', ', array_map(fn($col) => '$' . $col['Field'], $columnData));

// Generate the PHP code for the CRUD model based on the column data
$modelCode = <<<PHP
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

namespace App\Models;

use Pinga\Db\PdoDatabase;

class $className
{
    private PdoDatabase \$db;

    public function __construct(PdoDatabase \$db)
    {
        \$this->db = \$db;
    }

    public function getAll$className()
    {
        return \$this->db->select('SELECT * FROM $tableName');
    }

    public function get{$className}ById(\$id)
    {
        return \$this->db->select('SELECT * FROM $tableName WHERE id = ?', [\$id])->fetch();
    }

    public function create$className($createParams)
    {
        $quotedAssignments

        \$this->db->insert('INSERT INTO $tableName ($insertFields) VALUES ($insertValues)');

        return \$this->db->lastInsertId();
    }

    public function update$className(\$id, $updateFunctionParams)
    {
        $quotedAssignments

        \$this->db->update('UPDATE $tableName SET $updateParams WHERE id = ?', array_merge([\$id], [$updateArrayParams]));

        return true;
    }

    public function delete$className(\$id)
    {
        \$this->db->delete('DELETE FROM $tableName WHERE id = ?', [\$id]);

        return true;
    }
}
PHP;

// Save the generated PHP code to a file
$targetPath = __DIR__ . "/../app/Models/$className.php";
if (file_put_contents($targetPath, $modelCode) === false) {
    fwrite(STDERR, "Error: Failed to write model file to $targetPath\n");
    exit(1);
}

// Output a success message
echo "CRUD model for table '$tableName' generated successfully.\n";