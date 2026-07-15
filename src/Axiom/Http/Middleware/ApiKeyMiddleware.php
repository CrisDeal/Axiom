<?php 

namespace Ente\Middlewares;

use Axiom\Http\Router\Request;
use Axiom\Http\Router\Response;
use Axiom\Contracts\Http\MiddlewareInterface;
use Axiom\Exceptions\UnauthorizedException;

class AuthMiddleware implements MiddlewareInterface {
    public function handle(Request $req, Response $res, callable $next): mixed {
        $api_key = $req->getHeader('ente-api-key') ?? null;
        if (!$api_key) {
            throw new UnauthorizedException('No tienes permiso para acceder a este recurso');
        }

        $parts = explode('.', $api_key);
        if(count($parts) !== 2) {
            throw new UnauthorizedException('No tienes permiso para acceder a este recurso');
        }

        [$public, $secret] = $parts; // Buscar en DB seria lo "correcto".

        $validPublicKey = $_ENV['ENLIGHT_API_PUBLIC_KEY'];
        $validPrivateKey = $_ENV['ENLIGHT_API_PRIVATE_KEY'];

        if (!hash_equals($validPublicKey, $public) || !hash_equals($validPrivateKey, $secret)) {
            throw new UnauthorizedException('No tienes permiso para acceder a este recurso');
        }

        return $next();
    }
}