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

class CsrfViewMiddleware extends Middleware
{

    public function __invoke(Request $request, RequestHandler $handler)
    {
        $this->container->get('view')->getEnvironment()->addGlobal('csrf', [
            'field' => '
                <input type="hidden" name="'. $this->container->get('csrf')->getTokenNameKey() .'"
                 value="'. $this->container->get('csrf')->getTokenName() .'">
                <input type="hidden" name="'. $this->container->get('csrf')->getTokenValueKey() .'"
                 value="'. $this->container->get('csrf')->getTokenValue() .'">
            ',
        ]);
        $response = $handler->handle($request);
        return $response;
    }
}