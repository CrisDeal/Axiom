<?php
namespace Axiom\Exceptions;

/**
 * ValidationException
 *
 * Representa un error HTTP 422 Unprocessable Entity.
 * Se lanza cuando la petición está bien formada pero los datos
 * no pasan las reglas de validación del negocio — campos
 * requeridos vacíos, formatos incorrectos, valores fuera de rango.
 *
 * Diferencia con BadRequestException (400):
 *   400 — la petición en sí es inválida (mal formato, JSON roto).
 *   422 — la petición es válida pero los datos no pasan validación.
 *
 * Ejemplo de uso:
 *   throw new ValidationException();
 *   throw new ValidationException('El campo email no tiene un formato válido.');
 */
class ValidationException extends HttpException {

    /**
     * @param string $message Descripción del error de validación.
     *                        Por defecto: "Datos invalidos."
     */
    public function __construct(string $message = "Datos invalidos.") {
        parent::__construct($message, 422);
    }
};