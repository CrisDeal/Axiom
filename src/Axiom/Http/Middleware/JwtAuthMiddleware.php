<?php
namespace Axiom\Http\Middleware;

use Axiom\Http\Router\Request;
use Axiom\Http\Router\Response;
use Axiom\Contracts\Http\MiddlewareInterface;
use Axiom\Exceptions\UnauthorizedException;

class TokenAuthMiddleware implements MiddlewareInterface {

    public function handle(Request $req, Response $res, callable $next): mixed {
        $authorization = $req->getHeader('Authorization') ?? '';

        if (!str_starts_with($authorization, 'Bearer ')) {
            throw new UnauthorizedException('Token no proporcionado.');
        }

        $token = substr($authorization, 7);

        // Aquí irá la validación real del token (JWT u otro mecanismo).
        // Por ahora valida que no esté vacío.
        if (empty($token)) {
            throw new UnauthorizedException('Token inválido.');
        }

        // El token decodificado queda disponible para los controladores.
        $req->params['token'] = $token;

        return $next();
    }
}