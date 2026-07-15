<?php
namespace Axiom\Exceptions;

/**
 * ConflictException
 *
 * Representa un error HTTP 409 Conflict.
 * Se lanza cuando la petición entra en conflicto con el estado
 * actual del recurso — por ejemplo, intentar crear un registro
 * que ya existe, o una edición concurrente que genera colisión.
 *
 * Ejemplo de uso:
 *   throw new ConflictException();
 *   throw new ConflictException('El email ya está registrado.');
 */
class ConflictException extends HttpException {

    /**
     * @param string $message Descripción del conflicto.
     *                        Por defecto: "Conflicto."
     */
    public function __construct(string $message = "Conflicto.") {
        parent::__construct($message, 409);
    }
}