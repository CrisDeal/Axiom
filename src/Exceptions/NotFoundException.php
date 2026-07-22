<?php
namespace Axiom\Exceptions;

/**
 * NotFoundException
 *
 * Representa un error HTTP 404 Not Found.
 * Se lanza cuando el recurso o ruta solicitada no existe.
 * El Router la lanza automáticamente cuando ninguna ruta
 * registrada coincide con la petición entrante.
 *
 * Ejemplo de uso:
 *   throw new NotFoundException();
 *   throw new NotFoundException('El usuario con id 42 no existe.');
 */
class NotFoundException extends HttpException {

    /**
     * @param string $message Descripción del error.
     *                        Por defecto: "Pagina no encontrada."
     */
    public function __construct(string $message = 'Pagina no encontrada') {
        parent::__construct($message, 404);
    }
}