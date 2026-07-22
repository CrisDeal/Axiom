<?php
namespace Axiom\Exceptions;

/**
 * ForbiddenException
 *
 * Representa un error HTTP 403 Forbidden.
 * Se lanza cuando el cliente está autenticado pero no tiene
 * permisos suficientes para acceder al recurso solicitado.
 *
 * Diferencia con UnauthorizedException (401):
 *   401 — el cliente no está autenticado, debe identificarse.
 *   403 — el cliente está autenticado pero no tiene permiso.
 *
 * Ejemplo de uso:
 *   throw new ForbiddenException();
 *   throw new ForbiddenException('No tienes permiso para editar este recurso.');
 */
class ForbiddenException extends HttpException {

    /**
     * @param string $message Descripción del error.
     *                        Por defecto: "Acceso denegado."
     */
    public function __construct(string $message = "Acceso denegado.") {
        parent::__construct($message, 403);
    }
}