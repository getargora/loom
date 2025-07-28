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

use App\Models\Providers;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class ProvidersController extends Controller
{
    public function listProviders(Request $request, Response $response): Response
    {
        if ($_SESSION["auth_roles"] != 0) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        return view($response, 'admin/providers/index.twig');
    }

    public function createProvider(Request $request, Response $response): Response
    {
        if ($_SESSION["auth_roles"] != 0) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();
            $db = $this->container->get('db');

            $name = trim(filter_var($data['name'] ?? '', FILTER_SANITIZE_STRING));
            $type = in_array($data['type'] ?? '', ['domain', 'hosting', 'email', 'api', 'custom']) ? $data['type'] : 'custom';

            $api_connection = trim(filter_var($data['api_connection'] ?? '', FILTER_SANITIZE_URL));

            $credentials_raw = $data['credentials'] ?? '{}';
            $credentials = json_decode($credentials_raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->container->get('flash')->addMessage('error', 'Invalid JSON in credentials');
                return $response->withHeader('Location', '/provider/create')->withStatus(302);
            }
            $credentials = json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $pricing_raw = $data['pricing'] ?? '{}';
            $pricing = json_decode($pricing_raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->container->get('flash')->addMessage('error', 'Invalid JSON in pricing');
                return $response->withHeader('Location', '/provider/create')->withStatus(302);
            }
            $pricing = json_encode($pricing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $status = in_array($data['status'] ?? '', ['active', 'inactive', 'testing']) ? $data['status'] : 'active';

            try {
                $currentDateTime = new \DateTime();
                $created_at = $currentDateTime->format('Y-m-d H:i:s.v');
                $db->insert(
                    'providers',
                    [
                        'name' => $name,
                        'type' => $type,
                        'api_endpoint' => $api_connection,
                        'credentials' => $credentials,
                        'pricing' => $pricing,
                        'status' => $status,
                        'created_at' => $created_at
                    ]
                );
            } catch (Exception $e) {
                $this->container->get('flash')->addMessage('error', 'Database failure: ' . $e->getMessage());
                return $response->withHeader('Location', '/provider/create')->withStatus(302);
            }

            $this->container->get('flash')->addMessage('success', 'Provider ' . $name . ' has been created successfully on ' . $created_at);
            return $response->withHeader('Location', '/providers')->withStatus(302);
        }

        return view($response, 'admin/providers/create.twig');
    }

    public function editProvider(Request $request, Response $response, string $args): Response
    {
        if ($_SESSION["auth_roles"] != 0) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        $db = $this->container->get('db');
        $uri = $request->getUri()->getPath();

        if ($args) {
            $args = trim($args);

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                $this->container->get('flash')->addMessage('error', 'Invalid provider ID format');
                return $response->withHeader('Location', '/providers')->withStatus(302);
            }

            $provider = $db->selectRow('SELECT * FROM providers WHERE id = ?',
            [ $args ]);

            if ($provider) {
                $responseData = [
                    'provider' => $provider,
                    'currentUri' => $uri
                ];

                return view($response, 'admin/providers/edit.twig', $responseData);
            } else {
                // Provider does not exist, redirect to the providers view
                return $response->withHeader('Location', '/providers')->withStatus(302);
            }
        } else {
            // Redirect to the providers view
            return $response->withHeader('Location', '/providers')->withStatus(302);
        }
    }

    public function updateProvider(Request $request, Response $response, string $args): Response
    {
        if ($_SESSION["auth_roles"] != 0) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        
        if ($args) {
            $args = trim($args);

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                $this->container->get('flash')->addMessage('error', 'Invalid provider ID format');
                return $response->withHeader('Location', '/providers')->withStatus(302);
            }

            $data = $request->getParsedBody();
            $db = $this->container->get('db');
                
            $name = trim(filter_var($data['name'] ?? '', FILTER_SANITIZE_STRING));
            $api_connection = trim(filter_var($data['api_connection'] ?? '', FILTER_SANITIZE_URL));
                
            $credentials_raw = $data['credentials'] ?? '{}';
            $credentials = json_decode($credentials_raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->container->get('flash')->addMessage('error', 'Invalid JSON in credentials');
                return $response->withHeader('Location', '/providers/'.$args.'/edit')->withStatus(302);
            }
            $credentials = json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $pricing_raw = $data['pricing'] ?? '{}';
            $pricing = json_decode($pricing_raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->container->get('flash')->addMessage('error', 'Invalid JSON in pricing');
                return $response->withHeader('Location', '/providers/'.$args.'/edit')->withStatus(302);
            }
            $pricing = json_encode($pricing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $status = in_array($data['status'] ?? '', ['active', 'inactive', 'testing']) ? $data['status'] : 'active';
            
            try {
                $currentDateTime = new \DateTime();
                $created_at = $currentDateTime->format('Y-m-d H:i:s.v');
                $db->update(
                    'providers',
                    [
                        'name' => $name,
                        'api_endpoint' => $api_connection,
                        'credentials' => $credentials,
                        'pricing' => $pricing,
                        'status' => $status,
                        'created_at' => $created_at
                    ],
                    [
                        'id' => $args
                    ]
                );
            } catch (Exception $e) {
                $this->container->get('flash')->addMessage('error', 'Database failure: ' . $e->getMessage());
                return $response->withHeader('Location', '/providers/'.$args.'/edit')->withStatus(302);
            }

            $this->container->get('flash')->addMessage('success', 'Provider ' . $name . ' has been updated successfully on ' . $created_at);
            return $response->withHeader('Location', '/providers/'.$args.'/edit')->withStatus(302);    
        } else {
            // Redirect to the providers view
            return $response->withHeader('Location', '/providers')->withStatus(302);
        }
    }

    public function deleteProvider(Request $request, Response $response, string $args): Response
    {
        if ($_SESSION["auth_roles"] != 0) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        if ($args) {
            $args = trim($args);
            $db = $this->container->get('db');

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                $this->container->get('flash')->addMessage('error', 'Invalid provider ID format');
                return $response->withHeader('Location', '/providers')->withStatus(302);
            }

            $is_linked = $db->selectRow('SELECT id FROM services WHERE provider_id = ?',
            [ $args ]);

            if ($is_linked) {
                $this->container->get('flash')->addMessage('error', 'This provider cannot be deleted because it is linked to one or more services');
                return $response->withHeader('Location', '/providers')->withStatus(302);
            } else {
                $db->delete(
                    'providers',
                    [
                        'id' => $args
                    ]
                );

                $this->container->get('flash')->addMessage('success', 'Provider ' . $args . ' deleted successfully');
                return $response->withHeader('Location', '/providers')->withStatus(302);
            }
        } else {
            // Redirect to the providers view
            return $response->withHeader('Location', '/providers')->withStatus(302);
        }
    }
}