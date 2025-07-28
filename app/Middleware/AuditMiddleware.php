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
use Pinga\Session;

class AuditMiddleware extends Middleware
{

    public function __invoke(Request $request, RequestHandler $handler)
    {
        if (isset($_SESSION['auth_user_id'])) {
            $userId = (int)$_SESSION['auth_user_id'];
            $this->container->get('db')->exec("SET @audit_usr_id = $userId");
            $this->container->get('db')->exec("SET @audit_ses_id = " . crc32(\Pinga\Session\Session::id()));
        }
        return $handler->handle($request);
    }

}