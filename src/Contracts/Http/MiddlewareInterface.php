<?php
namespace Axiom\Contracts\Http;

use Axiom\Http\Router\Request;
use Axiom\Http\Router\Response;

/**
 * MiddlewareInterface
 *
 * Contrato que deben implementar todos los middlewares de Axiom.
 *
 * Un middleware es una capa intermedia que intercepta la petición
 * antes de que llegue al controlador — puede validar, transformar,
 * rechazar o enriquecer la petición y la respuesta.
 *
 * Los middlewares se encadenan en un pipeline donde cada uno
 * decide si pasa el control al siguiente llamando a $next(),
 * o corta el flujo enviando una respuesta directamente.
 *
 * Flujo del pipeline:
 *   Petición → Middleware 1 → Middleware 2 → Controlador
 *                          ←              ←
 *
 * Ejemplo de implementación:
 *
 *   class AuthMiddleware implements MiddlewareInterface {
 *       public function handle(Request $req, Response $res, callable $next): mixed {
 *           if (!$req->getHeader('Authorization')) {
 *               throw new UnauthorizedException();
 *           }
 *           return $next(); // pasa al siguiente middleware o controlador
 *       }
 *   }
 *
 * Registro en una ruta:
 *   $router->get('/admin', [AdminController::class, 'index'])
 *          ->middleware(AuthMiddleware::class);
 */
interface MiddlewareInterface {

    /**
     * Intercepta la petición HTTP entrante.
     *
     * Debe llamar a $next() para continuar el pipeline.
     * Si no llama a $next(), el controlador nunca se ejecuta —
     * úsalo para cortar el flujo en caso de error o redirección.
     *
     * @param Request  $req  Petición HTTP entrante.
     * @param Response $res  Respuesta HTTP saliente.
     * @param callable $next Siguiente capa del pipeline.
     * @return mixed         Resultado del pipeline.
     */
    public function handle(Request $req, Response $res, callable $next): mixed;
}