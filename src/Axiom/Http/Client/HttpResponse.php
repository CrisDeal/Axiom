<?php
namespace Axiom\Http\Client;

use Axiom\Exceptions\HttpClientException;

/**
 * HttpResponse
 * 
 * Representa la respuesta de una peticion HTTP.
 * Encapsula el status code, headers y body, ofreciendo
 * metodos semanticos para inspeccionarla y consumirla.
 * 
 * Uso basico:
 *  $response = $client->get('/users/1');
 * 
 *  $response->successful();    // true si status 2xx
 *  $response->status();        // 200
 *  $response->json();          // array con la respuesta JSON
 *  $response->throw()->json(); // Lanza excepcion si fallo, sino devuelve el JSON
 */
class HttpResponse {

    /**
     * @param int                   $status  Codigo de estado HTTP (ejemplo: 200, 404, 500)
     * @param string                $body    Cuerpo de la respuesta como string raw. 
     * @param array<string, string> $headers Headers de la respuesta HTTP
     */
    public function __construct(
        private int $status,
        private string $body,
        private array $headers = []
    ) {}

    /**
     * Retorna el HTTP status code de la respuesta.
     */
    public function status(): int {
        return $this->status;
    }

    /**
     * Verifica si la respuesta fue exitosa (status code 2xx).
     */
    public function successful(): bool {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Verifica si la respuesta fue exitosa con status 200 OK especificamente.
     */
    public function ok(): bool {
        return $this->status === 200;
    }

    /**
     * Verifica si la respuesta falló (status code 4xx 0 5xx).
     */
    public function failed(): bool {
        return !$this->successful();
    }

    /**
     * Verifica si la respuesta es un error del cliente (status code 4xx).
     */
    public function clientError(): bool {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Verifica si la respuesta es un error del servidor (status code 5xx).
     */
    public function serverError(): bool {
        return $this->status >= 500 && $this->status < 600;
    }

    /**
     * Verifica si la respuesta es 401 Unauthorized.
     */
    public function unauthorized(): bool {
        return $this->status === 401;
    }

    /**
     * Verifica si la respuesta es 404 Not Found.
     */
    public function notFound(): bool {
        return $this->status === 404;
    }

    /**
     * Decodifica el body como array asociativo.
     * Lanza una excepcion si el body no es un JSON valido.
     * 
     * Ejemplo:
     *  $data = $reponse->json(); // ['data' => [...]]
     * 
     * @return array <string, mixed> El body decodificado como array asociativo.
     * @throws \RuntimeException Si el body no es JSON valido o es null.
     */
    public function json(): array {
        $data = $this->decode(true);

        if(!is_array($data)) {
            throw new \RuntimeException(
                'La respuesta no contiene un JSON no es un array. Body: ' . $this->body
            );
        }

        return $data;
    }

    /**
     * Decodifica el body como objecto stdClass.
     * Util cuando prefieres acceder a por propiedade ($response->object()->name).
     * 
     * @return object El body decodificado como objecto stdClass.
     * @throws \RuntimeException Si el body no es JSON valido o es null.
     */
    public function object(): object {
        return (object) $this->decode(false);
    }

    /**
     * Retorna el body raw como string.
     * Util para respuestas que no son JSON (HTML, XML, CSV, etc).
     * 
     * Ejemplo:
     *  $html = $response->body();
     * 
     * @return string El body raw de la respuesta.
     */
    public function body(): string {
        return $this->body;
    }

    /**
     * Retorna todos los headers de la respuesta.
     * 
     * @return array<string, string> Headers de la respuesta.
     */
    public function headers(): array {
        return $this->headers;
    }

    /**
     * Retorna el valor de un header especifico.
     * La busqueda es case-insensitive - 'Content-Type' y 'content-type' son equivalentes.
     * 
     * Ejemplo:
     *  $contentType = $response->header('Content-Type');
     * 
     * @param string $key El nombre del header.
     * @return string|null El valor del header, o null si no existe.
     */
    public function header(string $key): ?string {
        $key = strtolower($key);
        $headers = array_change_key_case($this->headers, CASE_LOWER);
        return $headers[$key] ?? null;
    }

    /**
     * Lanza una excepcion si la respuesta fallo (4xx o 5xx).
     * Retorna $this si fue exitosa - permite encadenamiento fluido de metodos.
     * 
     * Ejemplo:
     *  $response = $client->get('/data')->throw()->json();
     *  // Si la peticion falla lanza excepcion, si no retorna el json.
     * 
     * @return static
     * @throws \RuntimeException Con el status code y el body del error.
     */
    public function throw(): static {
        if ($this->failed()) {
            throw new HttpClientException(
                "Error en servicio externo",
                $this
            );
        }
        return $this;
    }

    /**
     * Decodifica el body JSON internamente.
     * Centraliza la logica de decodificacion para json() y object().
     * 
     * @param bool $associative true retorna array, false retorna objecto.
     * @return mixed El body decodificado.
     * @throws \RuntimeException Si el JSON es invalido.
     */
    private function decode(bool $associative): mixed {
        $data = json_decode($this->body, $associative);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Respuesta JSON invalida: ' . json_last_error_msg() . '. Body: ' . $this->body
            );
        }

        return $data;
    }
}
