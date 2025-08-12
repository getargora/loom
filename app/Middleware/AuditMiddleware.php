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

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Pinga\Session;

class AuditMiddleware extends Middleware
{

    public function __invoke(Request $request, RequestHandler $handler)
    {
        if (isset($_SESSION['auth_user_id'])) {
            $userId = (int) $_SESSION['auth_user_id'];

            $db  = $this->container->get('db');

            // Figure out the driver name safely
            $driver = null;

            if (method_exists($db, 'getDriverName')) {
                // some wrappers expose this
                $driver = $db->getDriverName();
            } elseif (method_exists($db, 'getPdo')) {
                // Delight/Pinga sometimes expose the underlying PDO
                $pdo = $db->getPdo();
                if ($pdo instanceof \PDO) {
                    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                }
            } elseif ($db instanceof \PDO) {
                $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);
            } else {
                // Heuristic fallback: try a MySQL-only statement in a safe try/catch
                try {
                    // harmless in MySQL/MariaDB; will error on pgsql
                    $db->exec('DO 0'); // invalid in both; use something MySQL-specific:
                } catch (\Throwable $e) {
                    // as a better heuristic, try a MySQL variable; if it succeeds -> mysql
                    try {
                        $db->exec('SET @__probe := 1'); // MySQL/MariaDB only
                        $driver = 'mysql';
                    } catch (\Throwable $e2) {
                        $driver = 'pgsql'; // assume pg otherwise
                    }
                }
            }

            if ($driver === 'mysql') {
                // MySQL/MariaDB session vars
                $db->exec("SET @audit_usr_id = {$userId}");
                $db->exec("SET @audit_ses_id = " . crc32(\Pinga\Session\Session::id()));
            }
            elseif ($driver === 'pgsql') {
                // Use SELECT to avoid exec-on-SELECT differences across wrappers
                if (method_exists($db, 'query')) {
                    $sid = (string) crc32(\Pinga\Session\Session::id());
                    $db->query("SELECT set_config('audit.usr_id', '{$userId}', true)");
                    $db->query("SELECT set_config('audit.ses_id', '{$sid}', true)");
                }
            }
        }
        return $handler->handle($request);
    }

}