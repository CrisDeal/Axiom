<?php
declare(strict_types=1);

namespace Axiom\Contracts\Http;

use Axiom\Http\Client\HttpResponse;
use RuntimeException;

/**
 * Contrato para el cliente HTTP de Axiom.
 * 
 * Diseñado bajo el patrón de Interfaz Fluida (Fluent API).
 * Los métodos de configuración devuelven la propia instancia (`static`) 
 * para permitir el encadenamiento antes de ejecutar la petición.
 */
interface HttpClientInterface {

    /**
     * Establece la URL base para todas las peticiones de esta instancia.
     *
     * @param string $url URL base (ej. 'https://api.ejemplo.com/v1')
     * @return static
     */
    public function baseUrl(string $url): static;
    
    /**
     * Agrega o sobrescribe los encabezados HTTP para la petición.
     *
     * @param array<string, string> $headers Arreglo asociativo de encabezados
     * @return static
     */
    public function withHeaders(array $headers): static;

    /**
     * Helper para configurar la autorización mediante un Bearer Token.
     *
     * @param string $token Token de acceso
     * @return static
     */
    public function withToken(string $token): static;
    
    /**
     * Agrega parámetros globales a la cadena de consulta (query string).
     * Se combinarán con los parámetros específicos de cada petición.
     *
     * @param array<string, mixed> $query Parámetros de consulta
     * @return static
     */
    public function withQuery(array $query): static;

    /**
     * Desactiva la codificación URL (URL-encoding) estricta para los parámetros de consulta.
     * Útil para APIs legacy que no soportan caracteres codificados (ej. comas %2C).
     *
     * @return static
     */
    public function withoutQueryEncoding(): static;

    /**
     * Define el tiempo máximo de espera para la conexión y ejecución.
     *
     * @param int $seconds Tiempo en segundos
     * @return static
     */
    public function timeout(int $seconds): static;

    /**
     * Configura la política de reintentos en caso de fallos de red o errores 5xx.
     *
     * @param int $times Cantidad máxima de reintentos
     * @param int $delayMs Tiempo de espera entre reintentos en milisegundos
     * @return static
     */
    public function retry(int $times, int $delayMs): static;

    /**
     * Indica que los datos de la petición (body) se enviarán como JSON (application/json).
     * Suele ser el formato por defecto en APIs modernas.
     *
     * @return static
     */
    public function asJson(): static;

    /**
     * Indica que los datos de la petición se enviarán como un formulario web tradicional 
     * (application/x-www-form-urlencoded).
     *
     * @return static
     */
    public function asForm(): static;

    /**
     * Indica que los datos de la petición se enviarán en múltiples partes (multipart/form-data).
     * Obligatorio cuando se envían archivos físicos o imágenes.
     *
     * @return static
     */
    public function asMultipart(): static;

    /**
     * Define el tipo de contenido que se espera recibir del servidor (Header 'Accept').
     * Ayuda a prevenir que el servidor devuelva HTML cuando se espera otro formato.
     *
     * @param string $contentType Ej. 'text/html', 'application/xml'
     * @return static
     */
    public function accept(string $contentType): static;

    /**
     * Helper para indicar que se espera estrictamente una respuesta en formato JSON.
     *
     * @return static
     */
    public function acceptJson(): static;

    /**
     * Ejecuta una petición HTTP GET.
     *
     * @param string $url Ruta o URL completa a solicitar
     * @param array<string, mixed> $query Parámetros de consulta específicos para esta petición
     * @return HttpResponse
     * @throws RuntimeException En caso de fallos de red o si se exceden los reintentos
     */
    public function get(string $url, array $query = []): HttpResponse;

    /**
     * Ejecuta una petición HTTP POST.
     *
     * @param string $url Ruta o URL completa
     * @param array<string, mixed> $data Datos a enviar en el cuerpo de la petición
     * @return HttpResponse
     * @throws RuntimeException
     */
    public function post(string $url, array $data = []): HttpResponse;

    /**
     * Ejecuta una petición HTTP PUT para reemplazar un recurso.
     *
     * @param string $url Ruta o URL completa
     * @param array<string, mixed> $data Datos a actualizar
     * @return HttpResponse
     * @throws RuntimeException
     */
    public function put(string $url, array $data = []): HttpResponse;

    /**
     * Ejecuta una petición HTTP PATCH para actualizar parcialmente un recurso.
     *
     * @param string $url Ruta o URL completa
     * @param array<string, mixed> $data Datos parciales a actualizar
     * @return HttpResponse
     * @throws RuntimeException
     */
    public function patch(string $url, array $data = []): HttpResponse;

    /**
     * Ejecuta una petición HTTP DELETE.
     *
     * @param string $url Ruta o URL completa
     * @param array<string, mixed> $data Datos opcionales para enviar con la eliminación
     * @return HttpResponse
     * @throws RuntimeException
     */
    public function delete(string $url, array $data = []): HttpResponse;
}