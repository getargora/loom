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

class AuthMiddleware extends Middleware
{
    public function __invoke(Request $request, RequestHandler $handler)
    {
        $auth = $this->container->get('auth');

        if (! $auth->isLogin()) {
            $uri  = $request->getUri();
            $path = $uri->getPath();
            if (str_starts_with($path, '/orders/register') || str_starts_with($path, '/orders/transfer')) {
                $intent = $path;
                $q = $uri->getQuery();
                if ($q !== '') { $intent .= '?' . $q; }
                $_SESSION['login_intent'] = $intent;
            }

            return redirect()->route('login')->with('error', 'Access denied, you need to login.');
        }

        return $handler->handle($request);
    }
}