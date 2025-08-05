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

            $service = $db->selectRow('SELECT id, user_id, provider_id, order_id, type, status, config, registered_at, expires_at, updated_at FROM services WHERE id = ?',
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
        if ($request->getMethod() === 'POST') {
            // Retrieve POST data
            $data = $request->getParsedBody();
            $db = $this->container->get('db');
            $user_id = $data['user'];var_dump($args);
            var_dump($data);die();
        }
    }
    
    public function renewService(Request $request, Response $response, string $args): Response
    {
        if ($request->getMethod() === 'POST') {
            // Retrieve POST data
            $data = $request->getParsedBody();
            $db = $this->container->get('db');
            $user_id = $data['user'];var_dump($args);
            var_dump($data);die();
        } else {
            $db = $this->container->get('db');
            $uri = $request->getUri()->getPath();

            if ($args) {
                $args = trim($args);

                if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                    $this->container->get('flash')->addMessage('error', 'Invalid service ID format');
                    return $response->withHeader('Location', '/services')->withStatus(302);
                }

                $service = $db->selectRow('SELECT id, user_id, provider_id, order_id, type, status, config, registered_at, expires_at, updated_at FROM services WHERE id = ?',
                [ $args ]);

                if ($_SESSION["auth_roles"] != 0 && $_SESSION["auth_user_id"] !== $service["user_id"]) {
                    return $response->withHeader('Location', '/services')->withStatus(302);
                }

                if ($service) {
                    $config = json_decode($service['config'], true);

                    $provider = $db->selectRow(
                        'SELECT name, pricing FROM providers WHERE id = ?',
                        [ $service['provider_id'] ]
                    );
                    $pricing = json_decode($provider['pricing'], true);

                    $parts = explode('.', $config['domain']);
                    $tld = '.' . strtolower(end($parts));

                    $renewOptions = $pricing[$tld]['renew'] ?? [];

                    if (empty($renewOptions)) {
                        $maxYears = 0;
                    } else {
                        $availableYears = array_keys($renewOptions);
                        rsort($availableYears, SORT_NUMERIC);

                        $now = new \DateTimeImmutable();
                        $expires = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $service['expires_at']);
                        if (!$expires) {
                            $expires = new \DateTimeImmutable($service['expires_at']);
                        }

                        $interval = $now->diff($expires);
                        $daysRegistered = (int)$interval->format('%a');
                        $yearsRegistered = ceil($daysRegistered / 365.25);

                        $maxAllowed = 10 - $yearsRegistered;

                        $maxYears = 0;

                        if ($maxAllowed > 0 && !empty($renewOptions)) {
                            $availableYears = array_keys($renewOptions);
                            rsort($availableYears, SORT_NUMERIC);

                            foreach ($availableYears as $y) {
                                if ((int)$y <= $maxAllowed) {
                                    $maxYears = (int)$y;
                                    break;
                                }
                            }

                            if ($maxYears < $maxAllowed) {
                                $maxYears = max(0, $maxYears - $yearsRegistered);
                            }
                        }

                    }

                    $responseData = [
                        'service' => $service,
                        'provider' => $provider,
                        'config' => $config,
                        'currentUri' => $uri,
                        'maxYears' => $maxYears,
                    ];

                    return view($response, 'admin/services/renew.twig', $responseData);
                } else {
                    // Service does not exist, redirect to the services view
                    return $response->withHeader('Location', '/services')->withStatus(302);
                }
            } else {
                // Redirect to the services view
                return $response->withHeader('Location', '/services')->withStatus(302);
            }
        }
    }

    public function serviceLogs(Request $request, Response $response): Response
    {
        return view($response, 'admin/services/logs.twig');
    }
}