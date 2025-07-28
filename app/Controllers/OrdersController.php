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

use App\Models\Orders;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class OrdersController extends Controller
{
    public function listOrders(Request $request, Response $response): Response
    {
        return view($response, 'admin/orders/index.twig');
    }

    public function viewOrder(Request $request, Response $response, string $args): Response
    {
        $db = $this->container->get('db');
        $uri = $request->getUri()->getPath();

        if ($args) {
            $args = trim($args);

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                $this->container->get('flash')->addMessage('error', 'Invalid order ID format');
                return $response->withHeader('Location', '/orders')->withStatus(302);
            }

            $order = $db->selectRow('SELECT id, user_id, service_type, service_data, status, amount_due, currency, invoice_id, created_at, paid_at FROM orders WHERE id = ?',
            [ $args ]);

            if ($_SESSION["auth_roles"] != 0 && $_SESSION["auth_user_id"] !== $order["user_id"]) {
                return $response->withHeader('Location', '/orders')->withStatus(302);
            }

            if ($order) {
                $service_data = json_decode($order['service_data'], true);
                
                $user_name = $db->selectValue(
                    'SELECT username FROM users WHERE id = ?',
                    [ $order['user_id'] ]
                );

                $responseData = [
                    'order' => $order,
                    'service_data' => $service_data,
                    'user_name' => $user_name,
                    'currentUri' => $uri
                ];

                return view($response, 'admin/orders/view.twig', $responseData);
            } else {
                // Order does not exist, redirect to the orders view
                return $response->withHeader('Location', '/orders')->withStatus(302);
            }
        } else {
            // Redirect to the orders view
            return $response->withHeader('Location', '/orders')->withStatus(302);
        }
    }

    public function createOrder(Request $request, Response $response): Response
    {
        $db = $this->container->get('db');
        $providers = $db->select("SELECT id, name, type, api_endpoint, credentials, pricing FROM providers WHERE status = 'active'");

        $domainProducts = [];
        $otherProducts = [];

        foreach ($providers as &$provider) {
            $rawProducts = json_decode($provider['pricing'], true) ?? [];
            $credentials = json_decode($provider['credentials'], true) ?? [];
            $enrichedProducts = [];

            foreach ($rawProducts as $label => $actions) {
                $product = [
                    'type' => $provider['type'], // domain, server, etc.
                    'label' => $label,
                    'description' => ucfirst($provider['type']) . ' service: ' . $label,
                    'price' => $actions['register']['1'] ?? $actions['price'] ?? 0,
                    'billing' => $actions['billing'] ?? 'year',
                    'fields' => $credentials['required_fields'] ?? [],
                    'actions' => $actions,
                ];

                $enrichedProducts[$label] = $product;

                if ($product['type'] === 'domain') {
                    $domainProducts[] = ['provider' => $provider, 'product' => $product];
                } else {
                    $otherProducts[] = ['provider' => $provider, 'product' => $product];
                }
            }

            $provider['products'] = $enrichedProducts;
            $provider['credentials'] = json_decode($provider['credentials'], true) ?? [];
        }

        return $this->container->get('view')->render($response, 'admin/orders/create.twig', [
            'domainProducts' => $domainProducts,
            'otherProducts' => $otherProducts,
            'currency' => $_SESSION['_currency'] ?? 'EUR'
        ]);
    }

    public function payOrder(Request $request, Response $response, string $args): Response
    {
        return view($response, 'admin/orders/edit.twig', ['id' => $args['id'] ?? null]);
    }
    
    public function activateOrder(Request $request, Response $response, string $args): Response
    {
        return view($response, 'admin/orders/edit.twig', ['id' => $args['id'] ?? null]);
    }
    
    public function cancelOrder(Request $request, Response $response, string $args): Response
    {
        return view($response, 'admin/orders/edit.twig', ['id' => $args['id'] ?? null]);
    }
    
    public function retryOrder(Request $request, Response $response, string $args): Response
    {
        return view($response, 'admin/orders/edit.twig', ['id' => $args['id'] ?? null]);
    }

    public function deleteOrder(Request $request, Response $response, string $args): Response
    {
        return view($response, 'admin/orders/delete.twig', ['id' => $args['id'] ?? null]);
    }
}