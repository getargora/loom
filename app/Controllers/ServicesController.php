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

use App\Models\Services;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class ServicesController extends Controller
{
    public function listServices(Request $request, Response $response): Response
    {
        return view($response, 'admin/services/index.twig');
    }

    public function editService(Request $request, Response $response, string $args): Response
    {
        $db = $this->container->get('db');
        $uri = $request->getUri()->getPath();

        if ($args) {
            $args = trim($args);

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                $this->container->get('flash')->addMessage('error', 'Invalid service ID format');
                return $response->withHeader('Location', '/services')->withStatus(302);
            }

            $service = $db->selectRow('SELECT user_id, provider_id, order_id, type, status, config, registered_at, expires_at, updated_at FROM services WHERE id = ?',
            [ $args ]);

            if ($_SESSION["auth_roles"] != 0 && $_SESSION["auth_user_id"] !== $service["user_id"]) {
                return $response->withHeader('Location', '/services')->withStatus(302);
            }

            if ($service) {
                $config = json_decode($service['config'], true);
                
                $provider = $db->selectValue(
                    'SELECT name FROM providers WHERE id = ?',
                    [ $service['provider_id'] ]
                );

                $responseData = [
                    'service' => $service,
                    'provider' => $provider,
                    'config' => $config,
                    'currentUri' => $uri
                ];

                return view($response, 'admin/services/edit.twig', $responseData);
            } else {
                // Service does not exist, redirect to the services view
                return $response->withHeader('Location', '/services')->withStatus(302);
            }
        } else {
            // Redirect to the services view
            return $response->withHeader('Location', '/services')->withStatus(302);
        }
    }

    public function updateService(Request $request, Response $response, string $args): Response
    {
        return view($response, 'admin/services/edit.twig', ['id' => $args['id'] ?? null]);
    }

    public function serviceLogs(Request $request, Response $response): Response
    {
        return view($response, 'admin/services/logs.twig');
    }
}