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

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class MosapiController extends Controller
{

    public function mosapi(Request $request, Response $response): Response
    {
        if ($_SESSION["auth_roles"] != 0) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $flashError = $_SESSION['mosapi_error'] ?? null;
        unset($_SESSION['mosapi_error']);

        try {
            $cfg  = $this->configFromEnv();
            $data = $this->getData($cfg, false);

            return view($response, 'admin/mosapi/index.twig', [
                'cfg'  => $cfg,
                'data' => $data,
                'error' => $flashError,
            ]);
        } catch (\Throwable $e) {
            $cfg  = $this->safeConfigForError();
            return view($response, 'admin/mosapi/index.twig', [
                'cfg'  => $cfg,
                'data' => null,
                'error' => $flashError ?: $e->getMessage(),
            ]);
        }
    }

    public function mosapiRefresh(Request $request, Response $response): Response
    {
        if ($_SESSION["auth_roles"] != 0) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        try {
            $cfg  = $this->configFromEnv();
            $data = $this->getData($cfg, true);

            return view($response, 'admin/mosapi/index.twig', [
                'cfg'  => $cfg,
                'data' => $data,
                'error'=> null,
            ]);
        } catch (\Throwable $e) {
            $_SESSION['mosapi_error'] = $e->getMessage();
        }

        return $response->withHeader('Location', '/mosapi')->withStatus(302);
    }

    private function configFromEnv(): array
    {
        $ianaId = trim((string)envi('IANA_ID'));
        $user   = trim((string)envi('MOSAPI_USERNAME'));
        $pass   = (string)envi('MOSAPI_PASSWORD');
        $ip = trim((string)envi('BIND_IP'));

        if ($ip === '' || $ip === '1.2.3.4') {
            $ip = '';
        }

        if ($ianaId === '' || $user === '' || $pass === '') {
            throw new \RuntimeException('MoSAPI is not configured in .env (IANA_ID, MOSAPI_USERNAME, MOSAPI_PASSWORD).');
        }

        return [
            'base_url'     => 'https://mosapi.icann.org/rr/' . $ianaId,
            'username'     => $user,
            'password'     => $pass,
            'version'      => 'v2',
            'timeout'      => 10,
            'cache_ttl'    => 290,
            'source_ip'    => $ip,
            'show_domains' => false,
        ];
    }

    private function safeConfigForError(): array
    {
        return [
            'base_url' => 'https://mosapi.icann.org/rr/{IANA_ID}',
            'version'  => 'v2',
        ];
    }

    private function getData(array $cfg, bool $forceRefresh): array
    {
        $cacheKey = $this->cacheKey($cfg);

        if (!$forceRefresh) {
            $cached = $this->cacheGet($cacheKey, (int)$cfg['cache_ttl']);
            if ($cached !== null) {
                $cached['meta']['cache'] = 'HIT (file cache)';
                return $cached;
            }
        }

        $stateUrl   = rtrim($cfg['base_url'], '/') . '/' . $cfg['version'] . '/monitoring/state';
        $metricaUrl = rtrim($cfg['base_url'], '/') . '/' . $cfg['version'] . '/metrica/domainList/latest';

        $cookieFile = $this->tempFile('mosapi_cookie_', '.txt');

        try {
            $this->login($cfg, $cookieFile);

            $state   = $this->fetchJson($stateUrl, $cfg, $cookieFile);
            $metrica = $this->fetchJson($metricaUrl, $cfg, $cookieFile);

            $this->logout($cfg, $cookieFile);

            $payload = [
                'state'   => $state,
                'metrica' => $metrica,
                'meta'    => [
                    'cache'      => 'MISS (fresh)',
                    'fetched_at' => date('Y-m-d H:i:s'),
                ],
            ];

            $this->cacheSet($cacheKey, $payload);
            return $payload;
        } finally {
            if (is_file($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    }

    private function login(array $cfg, string $cookieFile): void
    {
        $url = rtrim($cfg['base_url'], '/') . '/login';

        $ch = curl_init($url);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $cfg['username'] . ':' . $cfg['password'],
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_TIMEOUT        => (int)$cfg['timeout'],
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ];

        if (!empty($cfg['source_ip'])) {
            $opts[CURLOPT_INTERFACE] = $cfg['source_ip'];
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = (string)curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Login request failed: ' . $err);
        }
        if ($status !== 200) {
            throw new \RuntimeException('Login failed (HTTP ' . $status . '): ' . $this->safeSnippet((string)$response));
        }
    }

    private function fetchJson(string $url, array $cfg, string $cookieFile): array
    {
        $ch = curl_init($url);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_TIMEOUT        => (int)$cfg['timeout'],
            CURLOPT_ENCODING       => 'gzip',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Accept-Encoding: gzip',
            ],
        ];

        if (!empty($cfg['source_ip'])) {
            $opts[CURLOPT_INTERFACE] = $cfg['source_ip'];
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = (string)curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Fetch failed: ' . $err);
        }

        if ($status !== 200) {
            throw new \RuntimeException(
                'Failed to fetch data (HTTP ' . $status . ') from ' . $url . ': ' . $this->safeSnippet((string)$response)
            );
        }

        $json = json_decode((string)$response, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Invalid JSON received from: ' . $url);
        }

        return $json;
    }

    private function logout(array $cfg, string $cookieFile): void
    {
        $url = rtrim($cfg['base_url'], '/') . '/logout';

        $ch = curl_init($url);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_TIMEOUT        => (int)$cfg['timeout'],
        ];

        if (!empty($cfg['source_ip'])) {
            $opts[CURLOPT_INTERFACE] = $cfg['source_ip'];
        }

        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        curl_close($ch);
    }

    private function cacheKey(array $cfg): string
    {
        return 'mosapi_' . hash('sha256', $cfg['base_url'] . '|' . $cfg['version'] . '|' . $cfg['username']);
    }

    private function storageDir(): string
    {
        $base = realpath(__DIR__ . '/../../cache') ?: (__DIR__ . '/../../cache');

        $dir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'mosapi_monitor';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }

    private function cacheFilePath(string $cacheKey): string
    {
        return $this->storageDir() . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    }

    private function cacheGet(string $cacheKey, int $ttlSeconds): ?array
    {
        $path = $this->cacheFilePath($cacheKey);
        if (!is_file($path)) {
            return null;
        }

        $mtime = @filemtime($path);
        if (!$mtime || (time() - $mtime) > $ttlSeconds) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['state']) || empty($data['metrica'])) {
            return null;
        }

        return $data;
    }

    private function cacheSet(string $cacheKey, array $payload): void
    {
        $path = $this->cacheFilePath($cacheKey);
        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function tempFile(string $prefix, string $suffix): string
    {
        $dir = $this->storageDir();

        $tmp = tempnam($dir, $prefix);
        if ($tmp === false) {
            return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8)) . $suffix;
        }

        $target = $tmp . $suffix;
        @rename($tmp, $target);

        return $target;
    }

    private function safeSnippet(string $s): string
    {
        $s = trim($s);
        if (strlen($s) > 1000) {
            return substr($s, 0, 1000) . '...';
        }
        return $s;
    }
}