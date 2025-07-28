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

namespace App\Lib;

class Redirect
{
    protected $name;
    protected $status;

    public function __construct($name=null,$status =301)
    {
        $this->name = $name;
        $this->status = $status;
    }
    public function __destruct()
    {
        $this->redirect();
    }

    public function to($name,$status =301)
    {
        $this->name = $name;
        $this->status = $status;
        return $this;
    }

    public function route($name, $params1 =[], $params2=[],$status =301)
    {
        $this->name = route($name,$params1,$params2);
        $this->status = $status;
        return $this;
    }

    public function with($type,  $message)
    {
        flash($type, $message);
        return $this;
    }

    public function redirect()
    {
        if (getenv('SWOOLE_ENABLED')) {
            // Running in Swoole
            if (!$this->response->isSent()) {
                $this->response = $this->response
                    ->withHeader('Location', $this->name)
                    ->withStatus($this->status);
            } else {
                $this->response->getBody()->write(
                    sprintf('<script>window.location.replace("%s");</script>', $this->name)
                );
            }
            return $this->response;
        } else {
            // Running in nginx/caddy/etc
            if (headers_sent() === false) {
                header('Location: ' . $this->name, true, $this->status);
                exit;
            }
            exit('window.location.replace("' . $this->name . '");');
        }
    }
    
}