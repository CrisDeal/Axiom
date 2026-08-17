<?php
declare(strict_types=1);

namespace Axiom\Http\Client;
use RuntimeException;

use Axiom\Exceptions\HttpClientException;

class HttpResponse {

    public function __construct(
        private int $status,
        private string $body,
        private array $headers = []
    ) {}

    public function status(): int {
        return $this->status;
    }


    public function successful(): bool {
        return $this->status >= 200 && $this->status < 300;
    }

    public function ok(): bool {
        return $this->status === 200;
    }


    public function failed(): bool {
        return !$this->successful();
    }


    public function clientError(): bool {
        return $this->status >= 400 && $this->status < 500;
    }

    public function serverError(): bool {
        return $this->status >= 500 && $this->status < 600;
    }

    public function unauthorized(): bool {
        return $this->status === 401;
    }

    public function notFound(): bool {
        return $this->status === 404;
    }

    /**
     * Decodifica la respuesta JSON.
     * Opcionalmente permite extraer una llave específica del primer nivel.
     *
     * @param string|null $key Llave a extraer (ej. 'data')
     * @param mixed $default Valor por defecto si la llave no existe
     * @return mixed
     * @throws RuntimeException Si el body no es un JSON válido
     */
    public function json(?string $key = null, mixed $default = null): mixed {
        $data = $this->decode(true);

        if(!is_array($data)) {
            throw new RuntimeException(
                'La respuesta no contiene un JSON no es un array. Body: ' . $this->body
            );
        }

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    public function object(): object {
        return (object) $this->decode(false);
    }

    public function body(): string {
        return $this->body;
    }

    /**
     * Devuelve todos los headers. 
     */
    public function headers(): array {
        return $this->headers;
    }

    /**
     * Obtiene el valor de un header específico en forma de string.
     * 
     * @param string $key Nombre del header
     * @return string|null
     */
    public function header(string $key): ?string {
        $key = strtolower($key);
        $headers = array_change_key_case($this->headers, CASE_LOWER);
        return $headers[$key] ?? null;
    }

    /**
     * Lanza una excepción si la petición falló (Status >= 400).
     * Permite el encadenamiento: $client->get('/api')->throw()->json();
     */
    public function throw(): static {
        if ($this->failed()) {
            throw new HttpClientException(
                "Error en servicio externo al intentar consumir la API. HTTP Status: {$this->status}",
                $this
            );
        }
        return $this;
    }

    private function decode(bool $associative): mixed {
        // Si el body está vacío, json_decode falla. Prevenimos esto devolviendo un array/objeto vacío.
        if (empty(trim($this->body))) {
            return $associative ? [] : new \stdClass();
        }

        $data = json_decode($this->body, $associative);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Respuesta JSON invalida: ' . json_last_error_msg() . '. Body: ' . $this->body);
        }

        return $data;
    }
}
