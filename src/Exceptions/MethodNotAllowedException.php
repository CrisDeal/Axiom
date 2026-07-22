<?php
namespace Axiom\Exceptions;

/**
 * BadRequestException
 *
 * Representa un error HTTP 400 Bad Request.
 * Se lanza cuando la petición del cliente es inválida —
 * parámetros incorrectos, formato inesperado, o datos faltantes
 * que no pasan validación básica.
 *
 * Ejemplo de uso:
 *   throw new BadRequestException();
 *   throw new BadRequestException('El campo email es obligatorio.');
 */
class MethodNotAllowedException extends HttpException {

    /**
     * @param string $message Descripción del error.
     *                        Por defecto: "Peticion invalida."
     */
    public function __construct(
        private array $allowedMethods,
        private string $message = "Método HTTP no permitido."
        ) {
        parent::__construct($message, 400);
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
};