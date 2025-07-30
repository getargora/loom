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

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class HomeController extends Controller
{
    public function index(Request $request, Response $response)
    {
        return view($response,'index.twig');
    }

    public function dashboard(Request $request, Response $response)
    {
        $db = $this->container->get('db');
        $isAdmin = $_SESSION["auth_roles"] == 0;
        $userId = $_SESSION["auth_user_id"];

        if ($isAdmin) {
            // Admin: total counts
            $userCount = $db->selectValue('SELECT COUNT(*) FROM users');
            $orderCount = $db->selectValue('SELECT COUNT(*) FROM orders');
            $invoiceCount = $db->selectValue('SELECT COUNT(*) FROM invoices');
            $ticketCount = $db->selectValue('SELECT COUNT(*) FROM support_tickets');

            $pendingOrders = $db->selectValue('SELECT COUNT(*) FROM orders WHERE status = ?', ['pending']);
            $unpaidInvoices = $db->selectValue('SELECT COUNT(*) FROM invoices WHERE payment_status = ?', ['unpaid']);
            $openTickets = $db->selectValue('SELECT COUNT(*) FROM support_tickets WHERE status = ?', ['Open']);
        } else {
            // Regular user: filtered by user_id
            $userCount = null; // Don't send this to view for users
            $orderCount = $db->selectValue('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$userId]);
            $invoiceCount = $db->selectValue('SELECT COUNT(*) FROM invoices WHERE user_id = ?', [$userId]);
            $ticketCount = $db->selectValue('SELECT COUNT(*) FROM support_tickets WHERE user_id = ?', [$userId]);

            $pendingOrders = $db->selectValue('SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = ?', [$userId, 'pending']);
            $unpaidInvoices = $db->selectValue('SELECT COUNT(*) FROM invoices WHERE user_id = ? AND payment_status = ?', [$userId, 'unpaid']);
            $openTickets = $db->selectValue('SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status = ?', [$userId, 'Open']);
        }

        return view($response, 'admin/dashboard/index.twig', [
            'userCount' => $userCount,
            'orderCount' => $orderCount,
            'invoiceCount' => $invoiceCount,
            'ticketCount' => $ticketCount,
            'pendingOrders' => $pendingOrders,
            'unpaidInvoices' => $unpaidInvoices,
            'openTickets' => $openTickets
        ]);
    }

    public function mode(Request $request, Response $response)
    {
        if (isset($_SESSION['_screen_mode']) && $_SESSION['_screen_mode'] == 'dark') {
            $_SESSION['_screen_mode'] = 'light';
        } else {
            $_SESSION['_screen_mode'] = 'dark';
        }
        $referer = $request->getHeaderLine('Referer');
        if (!empty($referer)) {
            return $response->withHeader('Location', $referer)->withStatus(302);
        }
        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    public function lang(Request $request, Response $response)
    {
        $data = $request->getQueryParams();
        if (!empty($data)) {
            $_SESSION['_lang'] = array_key_first($data);
        } else {
            unset($_SESSION['_lang']);
        }
        $referer = $request->getHeaderLine('Referer');
        if (!empty($referer)) {
            return $response->withHeader('Location', $referer)->withStatus(302);
        }
        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    public function selectTheme(Request $request, Response $response)
    {
        global $container;

        $data = $request->getParsedBody();
        $_SESSION['_theme'] = ($v = substr(trim(preg_replace('/[^\x20-\x7E]/', '', $data['theme-primary'] ?? '')), 0, 30)) !== '' ? $v : 'blue';

        $container->get('flash')->addMessage('success', 'Theme color has been set successfully');
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    public function clearCache(Request $request, Response $response): Response
    {
        if ($_SESSION["auth_roles"] != 0) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        $result = [
            'success' => true,
            'message' => 'Cache cleared successfully!',
        ];
        $cacheDir = realpath(__DIR__ . '/../../cache');

        try {
            // Check if the cache directory exists
            if (!is_dir($cacheDir)) {
                throw new RuntimeException('Cache directory does not exist.');
            }
            
            // Iterate through the files and directories in the cache directory
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                // Check if the parent directory name is exactly two letters/numbers long
                if (preg_match('/^[a-zA-Z0-9]{2}$/', $fileinfo->getFilename()) ||
                    preg_match('/^[a-zA-Z0-9]{2}$/', basename(dirname($fileinfo->getPathname())))) {
                    $action = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $action($fileinfo->getRealPath());
                }
            }

            // Delete the two-letter/number directories themselves
            $dirs = new \DirectoryIterator($cacheDir);
            foreach ($dirs as $dir) {
                if ($dir->isDir() && !$dir->isDot() && preg_match('/^[a-zA-Z0-9]{2}$/', $dir->getFilename())) {
                    rmdir($dir->getRealPath());
                }
            }

            // Clear Slim route cache if it exists
            $routeCacheFile = $cacheDir . '/routes.php';
            if (file_exists($routeCacheFile)) {
                unlink($routeCacheFile);
            }
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'Error clearing cache: ' . $e->getMessage(),
            ];
        }

        // Respond with the result as JSON
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}