#!/usr/bin/env php
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

if ($argc < 2) {
    echo "Usage: php create-controller.php [Name]\n";
    exit(1);
}

$name = ucfirst($argv[1]);
$class = "{$name}Controller";
$controllerFile = __DIR__ . "/../app/Controllers/{$class}.php";
$viewsDir = __DIR__ . "/../resources/views/admin/" . strtolower($name);
$lowerName = strtolower($name);

//
// Create Controller
//
if (file_exists($controllerFile)) {
    echo "Controller already exists: $controllerFile\n";
} else {
    $template = <<<PHP
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

use App\Models\\{$name};
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class $class extends Controller
{
    public function index(Request \$request, Response \$response): Response
    {
        return view(\$response, 'admin/$lowerName/index.twig');
    }

    public function view(Request \$request, Response \$response, array \$args): Response
    {
        return view(\$response, 'admin/$lowerName/view.twig', ['id' => \$args['id'] ?? null]);
    }

    public function create(Request \$request, Response \$response): Response
    {
        return view(\$response, 'admin/$lowerName/create.twig');
    }

    public function edit(Request \$request, Response \$response, array \$args): Response
    {
        return view(\$response, 'admin/$lowerName/edit.twig', ['id' => \$args['id'] ?? null]);
    }

    public function delete(Request \$request, Response \$response, array \$args): Response
    {
        return view(\$response, 'admin/$lowerName/delete.twig', ['id' => \$args['id'] ?? null]);
    }
}
PHP;

    file_put_contents($controllerFile, $template);
    echo "Created controller: $controllerFile\n";
}

//
// Create View Directory and Files
//
if (is_dir($viewsDir)) {
    echo "View directory already exists: $viewsDir\n";
} else {
    mkdir($viewsDir, 0775, true);

    $files = ['index', 'view', 'create', 'edit', 'delete'];
    foreach ($files as $file) {
        $path = "$viewsDir/$file.twig";
        file_put_contents($path, "<h1>$name - $file</h1>");
    }

    echo "Created Twig view directory and files in: $viewsDir\n";
}