<?php

namespace Axiom\Http\Middleware;

use Exception;
use Axiom\Security\AuthManager;
use Axiom\Http\Router\Request;
use Axiom\Http\Router\Response;

class AuthMiddleware
{
    public function __construct(
        private AuthManager $auth
    ) {}

    public function handle(Request $req, Response $res, callable $next): void
    {
        if (!$this->auth->check()) {
            throw new Exception(
                'Usuario no autenticado.'
            );
        }

        $next();
    }
}