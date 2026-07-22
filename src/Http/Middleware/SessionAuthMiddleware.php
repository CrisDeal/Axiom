<?php
namespace Axiom\Http\Middleware;

use Axiom\Http\Router\Request;
use Axiom\Http\Router\Response;
use Axiom\Contracts\Http\MiddlewareInterface;
use Axiom\Exceptions\UnauthorizedException;

class SessionAuthMiddleware implements MiddlewareInterface{
    
    public function __construct(
        private string $savePath = 'C:\Apache24\htdocs\confiabilidad\pages\TiposDeUsuarios\Usuario\Sesions',
        private string $sessionKey = 'email',
        private string $redirectPath = '/'
    ) {}

    public function handle(Request $req, Response $res, callable $next) : mixed {

        if ($this->savePath) {
            session_save_path($this->savePath);
        }

        if(session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if(!array_key_exists( $this->sessionKey, $_SESSION )) {
            // return $res->redirect($this->redirectPath);
            throw new UnauthorizedException();
        }

        $req->user = $_SESSION;

        $next();
    }
}
