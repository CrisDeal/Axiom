<?php
namespace Axiom\Exceptions;

/**
 * ServiceUnavailableException
 *
 * Representa un error HTTP 503 Service Unavailable.
 * Se lanza cuando el servidor no puede procesar la petición
 * temporalmente — mantenimiento, sobrecarga, o dependencias
 * externas no disponibles como base de datos o APIs de terceros.
 *
 * Ejemplo de uso:
 *   throw new ServiceUnavailableException();
 *   throw new ServiceUnavailableException('Base de datos no disponible.');
 */
class ServiceUnavailableException extends HttpException {

    /**
     * @param string $message Descripción del error.
     *                        Por defecto: "Servicio no disponible."
     */
    public function __construct(string $message = "Servicio no disponible.") {
        parent::__construct($message, 503);
    }
}