<?php
declare(strict_types=1);

namespace Axiom\Http\Client;

use Axiom\Contracts\Http\HttpClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;

/**
 * Implementación del cliente HTTP de Axiom utilizando Guzzle como motor interno (Patrón Adapter).
 * 
 * Esta clase es INMUTABLE. Cada método de configuración devuelve un clon (clone $this)
 * para garantizar que las peticiones no compartan estado accidentalmente si el cliente
 * se utiliza como un Singleton dentro del contenedor de dependencias.
 */
class GuzzleHttpClient implements HttpClientInterface {

    private ?string $baseUrl     = null;
    private array   $headers     = [];
    private array   $query       = [];
    private bool    $encodeQuery = true; // Por defecto, sí codificamos (es lo estándar y seguro)
    private int     $timeout     = 10;
    private int     $retries     = 0;
    private int     $retryDelay  = 0;

    /**
     * Define cómo se codificará el cuerpo (body) de las peticiones POST/PUT/PATCH.
     * Por defecto, las APIs modernas utilizan JSON.
     */
    private string  $bodyFormat = 'json';

    /**
     * @param Client $client Cliente base de Guzzle (inyectado para facilitar Testing/Mocks)
     */
    public function __construct(
        private Client $client
    ) {}

    /**
     * @inheritDoc
     * 
     * NOTA ARQUITECTÓNICA: Todos los métodos de configuración usan `clone $this`.
     * Esto hace que el cliente sea INMUTABLE. Así evitamos que si inyectamos este 
     * cliente de forma global, la petición 'A' afecte accidentalmente a la petición 'B'.
     */
    public function baseUrl(string $url): static {
        $cloned          = clone $this;
        $cloned->baseUrl = rtrim($url, '/'); // Previene doble slash (//) al armar la URL final
        return $cloned;
    }

    public function withHeaders(array $headers): static {
        $cloned          = clone $this;
        // Mezclamos conservando los anteriores y añadiendo/sobrescribiendo los nuevos
        $cloned->headers = array_merge($cloned->headers, $headers);
        return $cloned;
    }

    public function withToken(string $token): static {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ]);
    }

    public function withQuery(array $query): static {
        $cloned        = clone $this;
        $cloned->query = array_merge($cloned->query, $query);
        return $cloned;
    }

    public function withoutQueryEncoding(): static {
        $cloned = clone $this;
        $cloned->encodeQuery = false;
        return $cloned;
    }

    public function timeout(int $seconds): static {
        $cloned          = clone $this;
        $cloned->timeout = $seconds;
        return $cloned;
    }

    public function retry(int $times, int $delayMs): static {
        if ($times < 0) {
            throw new InvalidArgumentException('El numero de reintentos debe ser mayor o igual a 0.');
        }

        $cloned             = clone $this;
        $cloned->retries    = $times;
        $cloned->retryDelay = $delayMs;
        return $cloned;
    }


    // --- MÉTODOS DE FORMATO (CONTENT NEGOTIATION) ---

    public function asJson(): static {
        $cloned = clone $this;
        $cloned->bodyFormat = 'json';
        return $cloned;
    }

    // application/x-www-form-urlencoded
    public function asForm(): static {
        $cloned = clone $this;
        $cloned->bodyFormat = 'form_params'; 
        return $cloned;
    }

    // multipart/form-data (para archivos/imágenes)
    public function asMultipart(): static {
        $cloned = clone $this;
        $cloned->bodyFormat = 'multipart'; 
        return $cloned;
    }

    public function accept(string $contentType): static {
        return $this->withHeaders(['Accept' => $contentType]);
    }

    public function acceptJson(): static {
        return $this->withHeaders(['Accept' => 'application/json']);
    }

    // --- VERBOS HTTP (EJECUCIÓN) ---
    public function get(string $url, array $query = []): HttpResponse {
        return $this->send('GET', $url, ['query' => $query]);
    }

    // Delegamos los datos al motor central; él sabrá cómo empaquetarlos según el bodyFormat
    public function post(string $url, array $data = []): HttpResponse {
        return $this->send('POST', $url, [], $data);
    }

    public function put(string $url, array $data = []): HttpResponse {
        return $this->send('PUT', $url, [], $data);
    }

    public function patch(string $url, array $data = []): HttpResponse {
        return $this->send('PATCH', $url, [], $data);
    }

    public function delete(string $url, array $data = []): HttpResponse {
        return $this->send('DELETE', $url, [], $data);
    }

 /**
     * Motor central donde se preparan y ejecutan todas las peticiones hacia Guzzle.
     * 
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $url URL destino
     * @param array $options Configuraciones de la petición (queries específicas, headers)
     * @param array $data Cuerpo de la petición (si aplica)
     */
    private function send(string $method, string $url, array $options = [], array $data = []): HttpResponse {
        // 1. Construcción de la URL segura
        if($this->baseUrl) {
            $url = $this->baseUrl . '/' . ltrim($url, '/');
        }

        // Fusión de Query Parameters (Específicos sobreescriben a Globales)
        $mergedQuery = array_merge($this->query, $options['query'] ?? []);
        if (!empty($mergedQuery)) {
            if ($this->encodeQuery) {
                // Comportamiento normal: Guzzle recibe el array y hace el URL-encode seguro
                $options['query'] = $mergedQuery; 
            } else {
                // Armamos el string manualmente sin urlencode.
                // Al pasarle un STRING a Guzzle en 'query', este NO lo codifica.
                $pairs = [];
                foreach ($mergedQuery as $key => $value) {
                    if (is_array($value)) {
                        // Opción: Unir con comas (ej. ids=1,2,3) o saltarlo
                        $value = implode(',', $value);
                    }
                    $pairs[] = $key . '=' . $value;
                }
                $options['query'] = implode('&', $pairs); 
            }
        } else {
            unset($options['query']);
        }

        // Fusión de Headers (Específicos sobreescriben a Globales)
        $options['headers'] = array_merge(
            $this->headers,
            $options['headers'] ?? [],
        );

        // Inyección del Body según el formato configurado
        if (!empty($data)) {
            // Guzzle inyectará los datos automáticamente interpretando la llave (json, form_params, multipart)
            $options[$this->bodyFormat] = $data; 
        }

        $options['timeout']     = $this->timeout;

        // Desactivamos excepciones en errores de servidor (4xx y 5xx). 
        // El desarrollador debe recibir el HttpResponse y decidir cómo manejarlos en su lógica de negocio.
        $options['http_errors'] = false;

        $attempts = 0;

        // Loop para la política de reintentos (Retries)
        do {
            try {
                $response = $this->client->request($method, $url, $options);                

                // Retornamos nuestro propio objeto, desacoplando a Axiom de Guzzle
                return new HttpResponse(
                    $response->getStatusCode(),
                    (string) $response->getBody(),
                    $response->getHeaders()
                );
            } catch (RequestException $e) {
                // RequestException se lanza solo en fallos físicos de red (Timeout, DNS, etc.)
                $attempts++;

                if($attempts > $this->retries) {
                    throw $e; // Excedio reintentos, lanzar excepcion hacia arriba para que el desarrollador la maneje
                }

                // Esperamos antes de reintentar
                if($this->retryDelay > 0) {
                    usleep($this->retryDelay * 1000); // usleep usa microsegundos
                }
            }
        } while($attempts <= $this->retries);

        // Satisfacer el análisis estático
        throw new \LogicException('Unreachable code in HTTP Client send method.');
    }
}
