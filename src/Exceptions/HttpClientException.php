<?php
namespace Axiom\Exceptions;
use Axiom\Http\Client\HttpResponse;

/**
 * HttpClientException
 *
 * Se lanza cuando una peticion HTTP externa falla -
 * ya sea por error de conexion, timeout, o respuesta invalida
 * del servidor extenro.
 * 
 * El ErrorHandler la trata automaticamente como 503 Service Unavailable,
 * ocultando los detalles internos al usuario final y registrandolos en el log.
 * 
 * Se lanza automaticamente desde HttpResponse::throw()->json();
 * 
 * O manualmente desde el controlador:
 * if ($response->failed()) {
 *     throw new HttpClientException("La API externa fallo con status {$response->status()}");
 * }
 */
class HttpClientException extends HttpException {

    /**
     * @param string $message Descripción del fallo.
     * @param HttpResponse|null $response La respuesta HTTP que causo el error, si esta disponible.
     */
    public function __construct(
        string $message = "Error en servicio externo.", 
        private readonly ?HttpResponse $response = null
    ) {
        // Siempre 503 - un fallo en un servicio externo
        // no es culpa del cliente, es indisponibilidad del servidor.
        parent::__construct($message, 503);
    }

    /**
     * Retorna la respuesta HTTP que causo el error, si esta disponible.
     * Util para loggear detalles del error o para manejo personalizado.
     * 
     * @return HttpResponse|null La respuesta HTTP que causo el error, o null si no esta disponible.
     */
    public function getResponse(): ?HttpResponse {
        return $this->response;
    }
}