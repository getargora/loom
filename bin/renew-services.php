<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pinga\Db\PdoDataSource;
use Pinga\Db\PdoDatabase;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Setup DB
$dataSource = new PdoDataSource($_ENV['DB_DRIVER']);
$dataSource->setHostname($_ENV['DB_HOST']);
$dataSource->setPort((int)$_ENV['DB_PORT']);
$dataSource->setDatabaseName($_ENV['DB_DATABASE']);
$dataSource->setCharset('utf8mb4');
if ($_ENV['DB_USERNAME'] !== '') $dataSource->setUsername($_ENV['DB_USERNAME']);
if ($_ENV['DB_PASSWORD'] !== '') $dataSource->setPassword($_ENV['DB_PASSWORD']);

$db = PdoDatabase::fromDataSource($dataSource);

// Dummy Email & EPP Handlers
function sendReminderEmail(array $service, string $when): void {
    echo "Reminder: Service #{$service['id']} for user {$service['user_id']} expires in {$when} days\n";
}

function createRenewalOrder(PdoDatabase $db, array $service): void {
    $db->insert('orders', [
        'user_id'      => $service['user_id'],
        'service_type' => $service['type'],
        'service_data' => json_encode(['service_id' => $service['id']]),
        'status'       => 'pending',
        'amount_due'   => 10.00,
        'currency'     => 'EUR',
        'created_at'   => date('Y-m-d H:i:s'),
    ]);
    echo "Created renewal order for service ID {$service['id']}\n";
}

function updateNameservers(array $service): void {
    echo "Triggering EPP NS update for domain service ID {$service['id']}\n";
}

try {
    $services = $db->select(
        'SELECT * FROM services WHERE expires_at IS NOT NULL AND status IN (?, ?)',
        ['active', 'expired']
    );

    $now = new DateTime();

    foreach ($services as $service) {
        $expiresAt = new DateTime($service['expires_at']);
        $diffDays = (int)$now->diff($expiresAt)->format('%r%a');

        if (in_array($diffDays, [30, 14, 3, 1, -1], true)) {
            sendReminderEmail($service, (string)$diffDays);
        }

        if ($diffDays === 14) {
            $existing = $db->select(
                'SELECT COUNT(*) AS count FROM orders WHERE service_data LIKE ? AND status = ?',
                ['%"service_id":' . $service['id'] . '%', 'pending']
            );
            if ((int)($existing[0]['count'] ?? 0) === 0) {
                createRenewalOrder($db, $service);
            }
        }

        if ($service['type'] === 'domain' && $diffDays === -1) {
            updateNameservers($service);
        }

        if ($diffDays < -1 && $service['status'] === 'active') {
            $db->update(
                'services',
                ['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')],
                ['id' => $service['id']]
            );
            echo "Service ID {$service['id']} marked as expired\n";
        }
    }

} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}