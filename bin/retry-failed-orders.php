<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pinga\Db\PdoDataSource;
use Pinga\Db\PdoDatabase;
use Dotenv\Dotenv;

// Load .env
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

// Reprovisioning function
function reprovisionOrder(PdoDatabase $db, array $order): bool {
    echo "Re-attempting provisioning for order ID {$order['id']}\n";

    // Simulated success
    $success = true;

    if ($success) {
        $db->update('orders', [
            'status'     => 'active',
            'paid_at'    => $order['paid_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'id' => $order['id'],
        ]);

        $exists = $db->select('SELECT COUNT(*) AS count FROM services WHERE order_id = ?', [$order['id']]);
        if ((int)($exists[0]['count'] ?? 0) === 0) {
            $db->insert('services', [
                'user_id'    => $order['user_id'],
                'provider_id'=> null,
                'order_id'   => $order['id'],
                'type'       => $order['service_type'],
                'status'     => 'active',
                'config'     => $order['service_data'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            echo "Created related service for order ID {$order['id']}\n";
        }

        return true;
    }

    return false;
}

try {
    // 30-day threshold using PHP datetime
    $threshold = (new DateTime())->modify('-30 days')->format('Y-m-d H:i:s');

    $orders = $db->select(
        'SELECT * FROM orders WHERE status = ? AND created_at >= ?',
        ['failed', $threshold]
    );

    foreach ($orders ?? [] as $order) {
        $success = reprovisionOrder($db, $order);
        if (!$success) {
            echo "Order ID {$order['id']} still failed.\n";
            // Optionally log or notify
        }
    }

} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}