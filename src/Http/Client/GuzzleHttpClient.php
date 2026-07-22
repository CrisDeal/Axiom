<?php
namespace Axiom\Http\Client;

use Axiom\Contracts\Http\HttpClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * GuzzleHttpClient
 *
 * Implementacion de HttpClientInterface usando Guzzle como transporte HTTP.
 *
 * Implementa el patron inmutable - cada metodo de configuracion retorna
 * un clon modificado sin alterar la  instancia original. Esto permite
 * reutilizar una instancia base con diferentes configuraciones:
 *
 *  $base = $client->baseUrl('https://api.example.com')->withToken($token);
 *  $response1 = $base->get('/users/1');              // usa baseUrl y token
 *  $response2 = $base->timeout(30)->get('/users/2'); // Agrega timeout sin afectar $base
 *
 * Registro en el contenedor:
 *  $container->singleton(HttpClientInterface::class, fn() => new GuzzleHttpClient(
 *      new Client(['timeout' => 10])
 *  ));
 */
class GuzzleHttpClient implements HttpClientInterface {

    /**
     * Headers globales enviados en todas las peticiones.
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Query params globales fusionados en cada peticion.
     * Los params especificos de get() tienen precedencia sobre estos.
     * @var array<string, mixed>
     */
    private array $query = [];

    /**
     * Segundos antes de timeout. Por defecto 10 segundos.
     * @var int
     */
    private int $timeout = 10;

    /**
     * Numero maximo de reintentos en cada caso de fallo de red (5xx o excepcion de conexion).
     * Por defecto 0 (no reintentar).
     * @var int
     */
    private int $retries = 0;

    /**
     * Milisegundos de espera entre reintentos. Por defecto 0 (reintentos inmediatos).
     * @var int
     */
    private int $retryDelay = 0;

    /**
     * URL base antepuesta a todas las peticiones. Ejemplo: 'https://api.example.com'.
     * Si se establece, las rutas pasadas a get(), post(), etc se concatenan a esta URL base.
     * @var string|null
     */
    private ?string $baseUrl = null;

    /**
     * @param Client $client Instancia de GuzzleHttp\Client configurada.
     */
    public function __construct(
        private Client $client
    ) {}

    /**
     * Define la URL base para todas las peticiones.
     * Se antepone automaticamente a la URL de cada metodo HTTP.
     *
     * Ejemplo:
     *  $client->baseUrl('https://api.example.com')->get('/users');
     *  // Llama a https://api.example.com/users
     */
    public function baseUrl(string $url): static {
        $cloned          = clone $this;
        $cloned->baseUrl = rtrim($url, '/');
        return $cloned;
    }

    /**
     * Agrega headers globales fucionandolos con los existentes.
     * Se enviarn en todas las peticiones del cliente.
     *
     * @param array<string, string> $headers
     * @return static
     */
    public function withHeaders(array $headers): static {
        $cloned          = clone $this;
        $cloned->headers = array_merge($cloned->headers, $headers);
        return $cloned;
    }

    /**
     * Agrega el token Bearer al header Authorization.
     * Equivalente a withHeaders(['Authorization' => 'Bearer {$token}']).
     *
     * Ejemplo:
     *  $client->withToken('$jwtToken')->get('/profile');
     *
     * @param string $token Token de autenticacion.
     * @return static Para encadenamiento fluido de metodos.
     */
    public function withToken(string $token): static {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ]);
    }

    /**
     * Agrega query params globales para todas las peticiones.
     * Los params pasados directamente en get(), tienen precedencia sobre estos en caso de conflicto.
     *
     * Util para params que van en cada peticion: api_key, version, localez, etc.
     * Ejemplo:
     * $client->withQuery(['version' => 'v2'])->get('/users');
     *
     * @param array<string, mixed> $query
     * @return static
     */
    public function withQuery(array $query): static {
        $cloned        = clone $this;
        $cloned->query = array_merge($cloned->query, $query);
        return $cloned;
    }

    /**
     * Define el tiempo maximo de espera para la peticion en segundos.
     * Si la peticion supera este tiempo lanzara una excepcion de timeout.
     *
     * Ejemplo:
     *  $client->timeout(10)->get('/data'); // Timeout despues de 10 segundos
     *
     * @param int $seconds Segundos antes de timeout.
     * @return static Para encadenamiento fluido de metodos.     *
     */
    public function timeout(int $seconds): static {
        $cloned          = clone $this;
        $cloned->timeout = $seconds;
        return $cloned;
    }

    /**
     * Configura reintentos autoamaticos en cada fallo de red.
     * Solo reintenta cuando no hay respuesta del servidor (error de conexion).
     * Las respuestas 4xx y 5xx no se reintentan - se retornan como HttpResponse.
     *
     * @param int $times Numero maximo de reintentos.
     * @param int $delayMs Milisegundos de espera entre reintentos. Por defecto 0.
     * @throws \InvalidArgumentException Si $times es negativo.
     */
    public function retry(int $times, int $delayMs): static {
        if ($times < 0) {
            throw new \InvalidArgumentException('El numero de reintentos debe ser >= 0.');
        }

        $cloned             = clone $this;
        $cloned->retries    = $times;
        $cloned->retryDelay = $delayMs;
        return $cloned;
    }

    /**
     * Ejecuta una peticion GET.
     * Los $query pasados aqui tienen precedencia sobre los definidos en withQuery().
     *
     * @param string $url Ruta o URL completa del recurso.
     * @param array<string, mixed> $query Query params especificas de cada peticion.
     * @return HttpResponse Respuesta de la peticion.
     */
    public function get(string $url, array $query = [], bool $rawQuery = false): HttpResponse {
        if ($rawQuery) {
            $pairs = [];
            foreach ($query as $key => $value) {
                $pairs[] = $key . '=' . $value;
            }

            $url .= '?' . implode('&', $pairs);

            return $this->send('GET', $url);
        }

        return $this->send('GET', $url, [
            'query' => $query
        ]);
    }

    /**
     * Ejecuta una peticion POST con body JSON.
     * Los $data pasados aqui se envian en el body de la peticion.
      *
      * @param string $url Ruta o URL completa del recurso.
      * @param array<string, mixed> $data Datos a enviar en el body de la peticion.
      * @return HttpResponse Respuesta de la peticion.
     */
    public function post(string $url, array $data = [], string $type = 'json'): HttpResponse {
        return $this->send('POST', $url, [$type => $data]);
    }

    /**
     * Ejecuta una peticion PUT con body JSON.
     * Reemplaza completamente el recurso existente en el servidor.
     *
     * @param string $url Ruta o URL completa del recurso.
     * @param array<string, mixed> $data Datos a enviar en el body de la peticion.
     * @return HttpResponse Respuesta de la peticion.
     */
    public function put(string $url, array $data = []): HttpResponse {
        return $this->send('PUT', $url, ['json' => $data]);
    }

    /**
     * Ejecuta una peticion PATCH con body JSON.
     * Actualiza parcialmente el recurso existente en el servidor.
     *
     * @param string $url Ruta o URL completa del recurso.
     * @param array<string, mixed> $data Datos a enviar en el body de la peticion.
     * @return HttpResponse Respuesta de la peticion.
     */
    public function patch(string $url, array $data = []): HttpResponse {
        return $this->send('PATCH', $url, ['json' => $data]);
    }

    /**
     * Ejecuta una peticion DELETE.
     *
     * @param string $url Ruta o URL completa del recurso.
     * @param array<string, mixed> $data Datos a enviar en el body de la peticion.
     * @return HttpResponse Respuesta de la peticion.
     */
    public function delete(string $url, array $data = []): HttpResponse {
        return $this->send('DELETE', $url, ['json' => $data]);
    }

    /**
     * Ejecuta la peticion HTTP con logica re reintento.
     *
     * Solo reintenta en errores de red (sin respuesta del servidor).
     * Las respuestas 4xx y 5xx se retornan como HttpResponse - el caller
     * decide si lanzar una excepcion con ->throw().
     *
     * La precedencia de query params es:
     * params de get() > params de withQuery() (globales).
     *
     * @param string $method Metodo HTTP (GET, POST, etc).
     * @param string $url Ruta o URL completa del recurso.
     * @param array $options Opciones adicionales para Guzzle (headers, json, query, etc).
     * @return HttpResponse Respuesta de la peticion.     *
     */
    private function send(string $method, string $url, array $options = []): HttpResponse {
        if($this->baseUrl) {
            $url = $this->baseUrl . '/' . ltrim($url, '/');
        }

        if (empty($options['query'])) {
            unset($options['query']);
        } else {
            // Params de la peticion tienen precedencia sobre los globales.
            $options['query'] = array_merge(
                $this->query,           // globales - base.
                $options['query'] ?? [] // especificos - sobreescriben.
            );          
        }

        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            $this->headers
        );

        $options['timeout']     = $this->timeout;
        $options['http_errors'] = false; // Evitar excepciones automáticas de Guzzle para devolver siempre HttpResponse

        $attempts = 0;

        do {
            try {
                $response = $this->client->request($method, $url, $options);                

                return new HttpResponse(
                    $response->getStatusCode(),
                    (string) $response->getBody(),
                    $response->getHeaders()
                );
            } catch (RequestException $e) {
                // RequestException solo ocurre en fallos de red/conexion.
                // Con http_errors=false, las respuestas 4xx/5xx no llegan aqui.
                $attempts++;

                if($attempts > $this->retries) {
                    throw $e; // Excedio reintentos, lanzar excepcion al caller.
                }

                if($this->retryDelay > 0) {
                    usleep($this->retryDelay * 1000);
                }
            }
        } while($attempts <= $this->retries);
    }
}
