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

class ValidationErrorsMiddleware extends Middleware
{

    public function __invoke(Request $request, RequestHandler $handler)
    {
        $this->container->get('view')->getEnvironment()->addGlobal('errors', isset($_SESSION['errors']) ? $_SESSION['errors'] : '');
        unset($_SESSION['errors']);
        $response = $handler->handle($request);
        return $response;
    }
}