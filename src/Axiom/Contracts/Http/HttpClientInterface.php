<?php
namespace Axiom\Contracts\Http;

use Axiom\Http\Client\HttpResponse;

/**
 * HttpClientInterface
 * 
 * Contrato para clientes HTTP en Axiom.
 * Define una API fluida para construir y ejecutar peticiones HTTP
 * de forma expresiva, independientemente de la libreria subyacente.
 * 
 * La implementacion por defecto usa Guzzle internamente,
 * pero cualquier libreria puede usarse implementando esta interfaz.
 * 
 * Uso basico:
 * $response = $client->baseUrl('https://api.example.com')
 *                  ->withToken($token)
 *                  ->timeout(30)
 *                  ->retry(3, 1000)
 *                  ->get('/users);
 * 
 * $response->json();       // array con la respuesta JSON
 * $response->status();     // HTTP status code
 * $response->successful(); // true si status 2xx *  
 */
interface HttpClientInterface {

    /**
     * Define la URL base para todas las peticiones del cliente.
     * Se antepone automaticamente a la URL de cada metodo HTTP.
     * 
     * Ejemplo:
     *  $client->baseUrl('https://api.example.com')->get('/users');
     *  // Llama a https://api.example.com/users
     * 
     * @param  astring $url URL base. Ejemplo: 'https://api.example.com'
     * @return static       Para encadenamiento fluido de metodos.
     */
    public function baseUrl(string $url): static;
    
    /**
     * Agrega headers personalizados a la peticion.
     * Se fusionan con los headers existentes - no los reemplazan.
     * 
     * Ejemplo:
     *   $client->withHeaders(['X-API-Key' => 'secret', 'Accept' => 'application/json']);
     * 
     * @param  array<string, string> $headers Headers personalizados.
     * @return static              Para encadenamiento fluido de metodos.
     */
    public function withHeaders(array $headers): static;

    /**
     * Agrega un token Bearer al header Authorization.
     * Equivalente a withHeaders(['Authorization' => 'Bearer {token}']).
     * 
     * Ejemplo:
     *  $client->withToken('$jwtToken')->get('/profile');
     * 
     * @param  string $token Token de autenticacion.
     * @return static        Para encadenamiento fluido de metodos.
     */
    public function withToken(string $token): static;
    
    /**
     * Agrega query parameters blobales a todas las peticiones.
     * Se fusionan con los query params pasados directamente en get().
     * 
     * Ejemplo:
     *  $client->withQuery(['version' => 'v2'])->get('/users');
     *  // Llama a /users?version=v2
     * 
     * @param array<string, mixed> $query Parametros a agrupar.
     * @return static              Para encadenamiento fluido de metodos.
     */
    public function withQuery(array $query): static;

    /**
     * Define el tiempo maximo de espera para la peticion en segundos.
     * Si la peticiones supera este tiempo, lanza una excepcion.
     * 
     * Ejemplo:
     *  $client->timeout(10)->get('/data'); // Timeout despues de 10 segundos
     * 
     * @param  int $seconds Tiempo maximo de espera en segundos.
     * @return static       Para encadenamiento fluido de metodos. Por defecto 10 segundos.
     */
    public function timeout(int $seconds): static;

    /**
     * Configura reintentos automaticos en cada fallo.
     * Solo reintenta en errores de conexion o respuestas 5xx.
     * 
     * Ejemplo:
     *  $client->retry(3, 200)->get('/unstable-endpoint');
     *  // Reintenta hasta 3 veces con un retraso de 200ms entre intentos.
     * 
     * @param  int    $times   Numero maximo de intentos.
     * @param  int    $delayMs Milisegundos de espera entre reintentos.
     * @return static          Para encadenamiento fluido de metodos.
     */
    public function retry(int $times, int $delayMs): static;

    /**
     * Ejecuta una peticion GET.
     * 
     * @param string               $url   Ruta o URL completa del recurso.
     * @param array<string, mixed> $query Query params adicionales.
     * @return HttpResponse               Respuesta de la peticion.
     */
    public function get(string $url, array $query = [], bool $rawQuery = false): HttpResponse;

    /**
     * Ejecuta una peticion POST con body JSON.
     * 
     * @param string               $url   Ruta o URL completa del recurso.
     * @param array<string, mixed> $data  Datos a enviar en el body de la peticion.
     * @return HttpResponse               Respuesta de la peticion.
     */
    public function post(string $url, array $data = []): HttpResponse;

        /**
     * Ejecuta una peticion PUT con body JSON.
     * Reemplaza completamente el recurso existente en el servidor.
     * 
     * @param string               $url   Ruta o URL completa del recurso.
     * @param array<string, mixed> $data  Datos a enviar en el body de la peticion.
     * @return HttpResponse               Respuesta de la peticion.
     */
    public function put(string $url, array $data = []): HttpResponse;

    /**
     * Ejecuta una peticion PATCH con body JSON.
     * Actualiza parcialmente el recurso existente en el servidor.
     * 
     * @param string               $url   Ruta o URL completa del recurso.
     * @param array<string, mixed> $data  Datos a enviar en el body de la peticion.
     * @return HttpResponse               Respuesta de la peticion.
     */
    public function patch(string $url, array $data = []): HttpResponse;

    /**
     * Ejecuta una peticion DELETE.
     * 
     * @param string               $url   Ruta o URL completa del recurso.
     * @param array<string, mixed> $data  Datos a enviar en el body de la peticion.
     * @return HttpResponse               Respuesta de la peticion.
     */
    public function delete(string $url, array $data = []): HttpResponse;

}