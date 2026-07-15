<?php
namespace Axiom\Exceptions;

use Exception;

/**
 * HttpException
 *
 * Excepción base para todos los errores HTTP del framework.
 * Extiende la Exception nativa de PHP agregando un status code HTTP
 * que el ErrorHandler usa para determinar el tipo de respuesta.
 *
 * Todas las excepciones HTTP de Axiom heredan de esta clase:
 *   BadRequestException      → 400
 *   UnauthorizedException    → 401
 *   ForbiddenException       → 403
 *   NotFoundException        → 404
 *   ConflictException        → 409
 *   ValidationException      → 422
 *   ServiceUnavailableException → 503
 *
 * Puede usarse directamente para casos no cubiertos por las subclases:
 *   throw new HttpException('Método no permitido', 405);
 */
class HttpException extends Exception {

    /**
     * HTTP status code asociado a esta excepción.
     * Protected para que las subclases puedan leerlo si lo necesitan.
     */
    protected int $statusCode;

    /**
     * @param string $message    Descripción del error.
     * @param int    $statusCode HTTP status code. Por defecto 500.
     */
    public function __construct(string $message, int $statusCode = 500) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    /**
     * Retorna el HTTP status code asociado a esta excepción.
     * Usado por el ErrorHandler para construir la respuesta al cliente.
     */
    public function getStatusCode(): int {
        return $this->statusCode;
    }
}