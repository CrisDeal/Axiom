<?php
namespace Axiom\Http\Client;

use Axiom\Contracts\Http\HttpClientInterface;

/**
 * Fetch
 *
 * Fachada estática para el cliente HTTP de Axiom.
 * Provee una interfaz expresiva y simple para hacer peticiones HTTP
 * sin necesidad de resolver el cliente del contenedor DI manualmente.
 *
 * Internamente delega en la implementación de HttpClientInterface
 * registrada via FetchServiceProvider — por defecto GuzzleHttpClient.
 *
 * Cada llamada trabaja con un clon del cliente base, garantizando
 * que la configuración de una petición no afecte a las demás.
 *
 * Uso básico:
 *   // Petición simple
 *   $response = Fetch::get('https://api.example.com/users');
 *
 *   // Con configuración fluida
 *   $data = Fetch::baseUrl('https://api.example.com')
 *               ->withToken($token)
 *               ->timeout(5)
 *               ->get('/users')
 *               ->throw()
 *               ->json();
 *
 * Configuración en FetchServiceProvider:
 *   Fetch::setClient($container->make(HttpClientInterface::class));
 */
class Fetch {

    /**
     * Instancia base del cliente HTTP.
     * Se clona en cada llamada para garantizar inmutabilidad entre peticiones.
     */
    protected static HttpClientInterface $client;

    /**
     * Registra el cliente HTTP a usar por la fachada.
     * Llamado automáticamente por FetchServiceProvider en el boot.
     *
     * @param HttpClientInterface $client Implementación del cliente HTTP.
     */
    public static function setClient(HttpClientInterface $client): void {
        self::$client = $client;
    }

    /**
     * Retorna un clon del cliente base listo para usar.
     * El clon garantiza que cada petición parte de un estado limpio
     * sin heredar configuración de peticiones anteriores.
     *
     * @return HttpClientInterface
     * @throws \RuntimeException Si setClient() no fue llamado antes.
     */
    protected static function client(): HttpClientInterface {
        if (!isset(self::$client)) {
            throw new \RuntimeException(
                'Cliente HTTP no configurado. ' .
                'Asegúrate de registrar FetchServiceProvider antes de usar Fetch.'
            );
        }

        return clone self::$client;
    }

    // =========================================================
    // CONFIGURACIÓN — retornan el cliente para encadenamiento fluido
    // =========================================================

    /**
     * Define la URL base para la petición.
     * Permite encadenamiento: Fetch::baseUrl('https://api.com')->get('/users')
     */
    public static function baseUrl(string $url): HttpClientInterface {
        return self::client()->baseUrl($url);
    }

    /**
     * Agrega headers HTTP a la petición.
     * Permite encadenamiento: Fetch::withHeaders([...])->post('/endpoint', $data)
     */
    public static function withHeaders(array $headers): HttpClientInterface {
        return self::client()->withHeaders($headers);
    }

    /**
     * Agrega el token Bearer al header Authorization.
     * Permite encadenamiento: Fetch::withToken($jwt)->get('/profile')
     */
    public static function withToken(string $token): HttpClientInterface {
        return self::client()->withToken($token);
    }

    /**
     * Agrega query params globales a la petición.
     * Permite encadenamiento: Fetch::withQuery(['version' => 'v2'])->get('/users')
     */
    public static function withQuery(array $query): HttpClientInterface {
        return self::client()->withQuery($query);
    }

    /**
     * Define el timeout en segundos para la petición.
     * Permite encadenamiento: Fetch::timeout(5)->get('/slow-endpoint')
     */
    public static function timeout(int $seconds): HttpClientInterface {
        return self::client()->timeout($seconds);
    }

    /**
     * Configura reintentos automáticos en fallos de conexión.
     * Permite encadenamiento: Fetch::retry(3, 200)->get('/unstable-endpoint')
     */
    public static function retry(int $times, int $delayMs): HttpClientInterface {
        return self::client()->retry($times, $delayMs);
    }

    // =========================================================
    // HTTP METHODS — peticiones directas sin configuración previa
    // =========================================================

    /**
     * Ejecuta una petición GET.
     *
     * Ejemplo:
     *   $users = Fetch::get('https://api.example.com/users')->throw()->json();
     *
     * @param  string               $url   URL completa o relativa si se usó baseUrl.
     * @param  array<string, mixed> $query Query params adicionales.
     */
    public static function get(string $url, array $query = []): HttpResponse {
        return self::client()->get($url, $query);
    }

    /**
     * Ejecuta una petición POST con body JSON.
     *
     * Ejemplo:
     *   $response = Fetch::post('https://api.example.com/users', ['name' => 'Ana']);
     */
    public static function post(string $url, array $data = []): HttpResponse {
        return self::client()->post($url, $data);
    }

    /**
     * Ejecuta una petición PUT con body JSON.
     * Reemplaza completamente el recurso existente.
     */
    public static function put(string $url, array $data = []): HttpResponse {
        return self::client()->put($url, $data);
    }

    /**
     * Ejecuta una petición PATCH con body JSON.
     * Actualiza parcialmente el recurso existente.
     */
    public static function patch(string $url, array $data = []): HttpResponse {
        return self::client()->patch($url, $data);
    }

    /**
     * Ejecuta una petición DELETE.
     */
    public static function delete(string $url, array $data = []): HttpResponse {
        return self::client()->delete($url, $data);
    }
}