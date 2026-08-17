<?php
declare(strict_types=1);

namespace Axiom\Http\Client;

use Axiom\Contracts\Http\HttpClientInterface;

/**
 * Facade (Fachada) estática para el Cliente HTTP de Axiom.
 * Provee una interfaz global y expresiva para realizar peticiones HTTP
 * sin necesidad de inyectar el cliente manualmente en cada clase.
 */
class Fetch {

    protected static HttpClientInterface $client;

    /**
     * Inyecta la implementación concreta del cliente (ej. GuzzleHttpClient).
     * Esto normalmente se llama desde el FetchServiceProvider durante el arranque del framework.
     */
    public static function setClient(HttpClientInterface $client): void {
        self::$client = $client;
    }

    /**
     * Obtiene un CLON de la instancia base del cliente HTTP.
     * Al usar `clone`, garantizamos que cada cadena de peticiones (ej. Fetch::withToken()->get())
     * inicie con una instancia limpia, evitando que configuraciones de peticiones anteriores
     * contaminen las peticiones futuras.
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

    // --- MÉTODOS DE CONFIGURACIÓN (Redirigen al cliente clonado) ---

    public static function baseUrl(string $url): HttpClientInterface {
        return self::client()->baseUrl($url);
    }

    public static function withHeaders(array $headers): HttpClientInterface {
        return self::client()->withHeaders($headers);
    }

    public static function withToken(string $token): HttpClientInterface {
        return self::client()->withToken($token);
    }

    public static function withQuery(array $query): HttpClientInterface {
        return self::client()->withQuery($query);
    }

    public static function withoutQueryEncoding(): HttpClientInterface {
        return self::client()->withoutQueryEncoding();
    }

    public static function timeout(int $seconds): HttpClientInterface {
        return self::client()->timeout($seconds);
    }

    public static function retry(int $times, int $delayMs): HttpClientInterface {
        return self::client()->retry($times, $delayMs);
    }


    // --- MÉTODOS DE FORMATO ---

    public static function asJson(): HttpClientInterface {
        return self::client()->asJson();
    }

    public static function asForm(): HttpClientInterface {
        return self::client()->asForm();
    }

    public static function asMultipart(): HttpClientInterface {
        return self::client()->asMultipart();
    }

    public static function accept(string $contentType): HttpClientInterface {
        return self::client()->accept($contentType);
    }

    public static function acceptJson(): HttpClientInterface {
        return self::client()->acceptJson();
    }


    // --- VERBOS HTTP (Ejecutan la petición devolviendo HttpResponse) ---

    public static function get(string $url, array $query = []): HttpResponse {
        return self::client()->get($url, $query);
    }

    public static function post(string $url, array $data = []): HttpResponse {
        return self::client()->post($url, $data);
    }

    public static function put(string $url, array $data = []): HttpResponse {
        return self::client()->put($url, $data);
    }

    public static function patch(string $url, array $data = []): HttpResponse {
        return self::client()->patch($url, $data);
    }

    public static function delete(string $url, array $data = []): HttpResponse {
        return self::client()->delete($url, $data);
    }
}