<?php
namespace Axiom\Http\Router;

/**
 * Request
 * 
 * Representa una peticion HTTP entrante.
 * Encapsula todos los datos de la peticion - método, URL, headers,
 * query params, body y archivos - abstrayendo los superglobales de PHP
 * ($_SERVER, $_GET, $_POST, $_FILES) en una interfaz limpia y predecible.
 * 
 * Uso basico:
 * $req->method         // 'GET', 'POST', 'PUT', etc.
 * $req->url            // '/api/users'
 * $req->query['page']  // query param ?page=2
 * $req->body['email']  // campo del body
 * $req->input('email') // busca en body y query
 * $req->getHeader('Authorization')
 * $req->wantsJson()
 */
class Request {

    /**
     * Parámetros de ruta dinámicos.
     * Poblado por el Router al hacer march de la URL
     * Ejemplo: en la ruta /users/{id}, $params['id'] = '42'
     * 
     * @var array<string, string>
     */
    public array  $params = [];

    /**
     * Parámetros de query string (?clave=valor).
     * Mapeado directamente desde $_GET.
     *
     * @var array<string, mixed>
     */
    public array  $query = [];

    /**
     * Cuerpo de la petición parseado.
     * Para peticiones JSON contiene el array decodificado.
     * Para form-data contiene $_POST.
     *
     * @var array<string, mixed>
     */    
    public array  $body = [];

    /**
     * Archivos subidos en la petición.
     * Mapeado directamente desde $_FILES.
     *
     * @var array<string, mixed>
     */
    public array  $files = [];

    /**
     * Headers HTTP normalizados a minúsculas con guiones.
     * Ejemplo: 'content-type', 'accept', 'x-requested-with'
     * Se accede a través de getHeader() — no directamente.
     *
     * @var array<string, string>
     */
    public array $headers = [];

    /**
     * Método HTTP de la petición en mayúsculas.
     * Ejemplo: 'GET', 'POST', 'PUT', 'DELETE', 'PATCH'
     */
    public string $method;

    /**
     * Ruta URL sin query string.
     * Ejemplo: para /users?page=2 almacena '/users'
     */
    public string $url;

    /**
     * Inicializa la petición leyendo los superglobales de PHP.
     * Se ejecuta una sola vez al instanciar — toda la normalización
     * ocurre aquí para que el resto de la app trabaje con datos limpios.
     *
     * @throws \InvalidArgumentException Si el body es JSON malformado.
     */
    public function __construct() {
        $currentUrl   = $_SERVER['REQUEST_URI'] ?? '/';
        $this->url    = parse_url($currentUrl, PHP_URL_PATH ?? '/');
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query  = $_GET;
        $this->files  = $_FILES;

        // Los headers deben parsearse antes que el body,
        // porque parseBody() necesita leer Content-Type.
        $this->headers = $this->parseHeaders();
        $this->body    = $this->parseBody();
    }

    /**
     * Retorna el valor de un header HTTP por nombre.
     * La búsqueda es case-insensitive — 'Accept' y 'accept' son equivalentes.
     *
     * @param  string $name Nombre del header. Ejemplo: 'Authorization'
     * @return string|null Valor del header, o null si no existe.
     */
    public function getHeader(string $name): ?string {
        $key = strtolower($name);
        return $this->headers[$key] ??  null;
    }

    /**
     * Determina si el cliente espera una respuesta en formato JSON.
     *
     * Revisa tres señales en orden:
     *   1. Header Accept contiene 'application/json'
     *   2. Header X-Requested-With es 'XMLHttpRequest' (fetch/axios)
     *   3. Content-Type de la petición es 'application/json'
     *
     * Esto cubre clientes que no envían Accept explícitamente
     * pero sí identifican su petición como AJAX o JSON.
     */
    public function wantsJson(): bool {
        $accept      = $this->getHeader('accept') ?? '';
        $xhr         = $this->getHeader('x-requested-with') ?? '';
        $contentType = $this->getHeader('content-type') ?? '';

        return str_contains($accept, 'application/json')
            || strtolower($xhr) === 'xmlhttprequest'
            || str_contains($contentType, 'application/json');
    }

    /**
     * Acceso unificado a un valor del body o query string.
     * Busca primero en el body, luego en query params.
     * Útil cuando el origen del dato no importa al controlador.
     *
     * Ejemplo:
     *   // GET /users?role=admin  o  POST con body {"role":"admin"}
     *   $request->input('role')         // 'admin' en ambos casos
     *   $request->input('missing', 0)   // retorna 0 si no existe
     *
     * @param  string $key     Nombre del campo a buscar.
     * @param  mixed  $default Valor a retornar si el campo no existe.
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Extrae y normaliza todos los headers HTTP desde $_SERVER.
     *
     * PHP expone los headers con el prefijo HTTP_ y guiones bajos.
     * Ejemplo: el header 'Accept-Language' llega como 'HTTP_ACCEPT_LANGUAGE'.
     * Este método los convierte a minúsculas con guiones: 'accept-language'.
     *
     * Excepción: Content-Type y Content-Length no tienen prefijo HTTP_
     * en $_SERVER — se manejan por separado.
     *
     * @return array<string, string> Headers normalizados.
     */
    private function parseHeaders(): array {
        $headers = [];
        foreach($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                // HTTP_ACCEPT_LANGUAGE -> accept-language
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }

            // Content-Type y Content-Length son la excepción —
            // PHP los expone sin prefijo HTTP_ en $_SERVER.
            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    /**
     * Parsea el cuerpo de la petición según el Content-Type.
     *
     * - application/json : lee php://input y decodifica el JSON.
     *   Lanza una excepción si el JSON está malformado.
     *
     * - Cualquier otro tipo (form-data, multipart, etc.):
     *   retorna $_POST directamente.
     *
     * @return array<string, mixed> Body parseado.
     * @throws \InvalidArgumentException Si el JSON está malformado.
     */
    private function parseBody(): array {
        $contentType = $this->getHeader('content-type') ?? '';

        if(str_contains($contentType, 'application/json')) {
            $rawBody = file_get_contents('php://input');
            $decoded = json_decode($rawBody, true);

            if(json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException(
                    'JSON malformado: ' . json_last_error_msg()
                );
            }

            return $decoded ?? [];
        }

        // Form-data estándar — PHP ya lo parsea automáticamente en $_POST.
        return $_POST;
    }
}