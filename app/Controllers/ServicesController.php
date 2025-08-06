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
use Utopia\Domains\Domain as uDomain;

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

            if ($args) {
                $args = trim($args);

                if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                    $this->container->get('flash')->addMessage('error', 'Invalid service ID format');
                    return $response->withHeader('Location', '/services')->withStatus(302);
                }

                $service = $db->selectRow('SELECT id, user_id, type, config FROM services WHERE id = ?',
                [ $args ]);

                if ($_SESSION["auth_roles"] != 0 && $_SESSION["auth_user_id"] !== $service["user_id"]) {
                    return $response->withHeader('Location', '/services')->withStatus(302);
                }

                if ($service) {
                    if ($service['type'] === 'domain') {
                        $config = json_decode($service['config'], true);
                        $domains = [$config['domain']];
                        $domainData = getDomainConfig($domains, $db);

                        if (empty($domainData) || !isset($domainData[0]['tld'])) {
                            $this->container->get('flash')->addMessage('error', 'Error checking domain');
                            return $response->withHeader('Location', '/services/'.$args.'/edit')->withStatus(302);
                        }

                        $registryType = getRegistryExtensionByTld('.'.$domainData[0]['tld']);

                        try {
                            $epp = connectToEpp(
                                $registryType,
                                $domainData[0]['host'],
                                $domainData[0]['port'],
                                $domainData[0]['cafile'] ?? '',
                                $domainData[0]['cert_file'],
                                $domainData[0]['key_file'],
                                $domainData[0]['passphrase'] ?? '',
                                $domainData[0]['username'],
                                $domainData[0]['password']
                            );

                            if (!$epp) {
                                $this->container->get('flash')->addMessage('error', 'Failed to connect to EPP server');
                                return $response->withHeader('Location', '/services/'.$args.'/edit')->withStatus(302);
                            }

                            $params = [
                                'domainname' => $config['domain'],
                            ];

                            if (!empty($data['authInfo'])) {
                                $params['authInfo'] = $data['authInfo'];
                            }

                            for ($i = 1; $i <= 13; $i++) {
                                $key = 'ns' . $i;
                                if (!empty($data[$key])) {
                                    $params[$key] = $data[$key];
                                }
                            }

                            $messages = [];

                            if (!empty($params['authInfo'])) {
                                $domainUpdateAuthinfo = $epp->domainUpdateAuthinfo($params);

                                if (array_key_exists('error', $domainUpdateAuthinfo)) {
                                    $messages[] = 'AuthInfo update failed: ' . $domainUpdateAuthinfo['error'];
                                } else {
                                    $messages[] = 'AuthInfo update successful.';
                                    $config['authcode'] = $params['authInfo'];
                                }
                            }

                            $hasNs = false;
                            for ($i = 1; $i <= 13; $i++) {
                                if (!empty($params['ns' . $i])) {
                                    $hasNs = true;
                                    break;
                                }
                            }

                            if ($hasNs) {
                                $domainUpdateNS = $epp->domainUpdateNS($params);

                                if (array_key_exists('error', $domainUpdateNS)) {
                                    $messages[] = 'Nameserver update failed: ' . $domainUpdateNS['error'];
                                } else {
                                    $messages[] = 'Nameserver update successful.';
                                    $config['nameservers'] = [];

                                    for ($i = 1; $i <= 13; $i++) {
                                        $key = 'ns' . $i;
                                        if (!empty($params[$key])) {
                                            $config['nameservers'][] = $params[$key];
                                        }
                                    }
                                }
                            }

                            $db->update(
                                'services',
                                ['config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)],
                                ['id' => $args]
                            );

                            $type = 'success';
                            foreach ($messages as $m) {
                                if (str_contains($m, 'failed')) {
                                    $type = 'error';
                                    break;
                                }
                            }

                            $epp->logout();

                            // Flash all messages joined
                            $this->container->get('flash')->addMessage($type, implode(' ', $messages));
                            return $response->withHeader('Location', '/services/' . $args . '/edit')->withStatus(302);
                        } catch (\Throwable $e) {
                            $this->container->get('flash')->addMessage('error', 'EPP error: ' . $e->getMessage());
                            return $response->withHeader('Location', '/services/' . $args . '/edit')->withStatus(302);
                        }
                    } else {
                        $this->container->get('flash')->addMessage('error', 'Service type not yet implemented');
                        return $response->withHeader('Location', '/services/' . $args . '/edit')->withStatus(302);
                    }
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
    
    public function renewService(Request $request, Response $response, string $args): Response
    {
        if ($request->getMethod() === 'POST') {
            // Retrieve POST data
            $data = $request->getParsedBody();
            $db = $this->container->get('db');
            
            $args = trim($args);

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                $this->container->get('flash')->addMessage('error', 'Invalid service ID format');
                return $response->withHeader('Location', '/services')->withStatus(302);
            }

            $userId = $_SESSION['auth_user_id'];
            $currency = $_SESSION['_currency'];
            $domainName = $_SESSION['domains_to_renew'][0];
            $amount = $_SESSION['domains_to_renew_price'][0];
            $years = (int) ($data['renewalYears'] ?? 1);

            $domain = new uDomain($domainName);
            $tld = '.' . strtolower($domain->getTLD());

            try {
                $db->beginTransaction();

                // Get provider name
                $providers = $db->select('SELECT name, pricing FROM providers WHERE status = ?', [ 'active' ]);

                $providerName = null;

                foreach ($providers as $provider) {
                    $pricing = json_decode($provider['pricing'], true);
                    if (isset($pricing[$tld])) {
                        $providerName = $provider['name'];
                        break; // stop at the first match
                    }
                }

                // Get billing contact ID
                $billingContactId = $db->selectValue(
                    'SELECT id FROM users_contact WHERE user_id = ? AND type = ? LIMIT 1',
                    [ $userId, 'billing' ]
                );

                // Insert into invoices
                $db->insert('invoices', [
                    'user_id' => $userId,
                    'billing_contact_id' => $billingContactId,
                    'issue_date' => date('Y-m-d H:i:s'),
                    'due_date' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                    'total_amount' => $amount,
                    'payment_status' => 'unpaid',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $invoiceId = $db->getLastInsertId();

                $db->update('invoices', [
                    'invoice_number' => $invoiceId
                ],
                [
                    'id' => $invoiceId
                ]
                );

                // Build service_data
                $serviceData = json_encode([
                    'type' => 'domain_renew',
                    'domain' => $domainName,
                    'years' => $years,
                    'tld' => $tld,
                    'provider' => $providerName,
                    'error_message' => null,
                    'notes' => 'Customer requested domain renewal'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

                // Insert into orders
                $db->insert('orders', [
                    'user_id' => $userId,
                    'service_type' => 'domain.renew',
                    'service_data' => $serviceData,
                    'status' => 'pending',
                    'amount_due' => $amount,
                    'currency' => $currency,
                    'invoice_id' => $invoiceId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
           
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $this->container->get('flash')->addMessage('error', 'Database failure during order creation: ' . $e->getMessage());
                return $response->withHeader('Location', '/services/'.$args.'/edit')->withStatus(302);
            }

            unset($_SESSION['domains_to_renew']);
            unset($_SESSION['domains_to_renew_price']);
            $this->container->get('flash')->addMessage('success','Renewal order for domain ' . $domainName . ' has been created. Please proceed with payment to complete the order.');
            return $response->withHeader('Location', '/invoice/'.$invoiceId)->withStatus(302);
        }

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

                $_SESSION['domains_to_renew'] = [$config['domain']];
                $_SESSION['domains_to_renew_price'] = [$pricing[$tld]['renew']['1']] ?? null;

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

    public function serviceLogs(Request $request, Response $response): Response
    {
        return view($response, 'admin/services/logs.twig');
    }
}