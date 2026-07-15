<?php
namespace Axiom\Exceptions;

/**
 * UnauthorizedException
 *
 * Representa un error HTTP 401 Unauthorized.
 * Se lanza cuando el cliente intenta acceder a un recurso
 * protegido sin estar autenticado, o con credenciales inválidas.
 *
 * Diferencia con ForbiddenException (403):
 *   401 — el cliente no está autenticado, debe identificarse.
 *   403 — el cliente está autenticado pero no tiene permiso.
 *
 * Ejemplo de uso:
 *   throw new UnauthorizedException();
 *   throw new UnauthorizedException('Token inválido o expirado.');
 */
class UnauthorizedException extends HttpException {

    /**
     * @param string $message Descripción del error.
     *                        Por defecto: "No autorizado."
     */
    public function __construct(string $message = "No autorizado.") {
        parent::__construct($message, 401);
    }
}