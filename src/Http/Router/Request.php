<?php
namespace Axiom\Http\Router;

/**
 * HTTP Request
 * 
 * Encapsula la petición HTTP entrante. Proporciona una interfaz orientada a objetos
 * para acceder a los datos superglobales de PHP ($_GET, $_POST, $_SERVER, etc.).
 * 
 * Diseño Semi-Inmutable:
 * La mayoría de las propiedades son `readonly` para evitar que middlewares o 
 * controladores modifiquen el estado original de la petición accidentalmente.
 */
class Request {

    public readonly string $url;
    public readonly string $method;
    public readonly array  $query;
    public readonly array  $body;
    public readonly array  $files;
    public readonly array  $headers;

    /**
     * @var array Parámetros dinámicos de la ruta (ej: ['id' => 5]).
     * Esta propiedad NO es readonly porque el MainRouter
     * necesita inyectarle los valores después de instanciar esta clase 
     * durante la fase de "Route Matching".
     */
    public array  $params = [];


    public function __construct() {
        $currentUrl   = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Limpiamos la URL de query strings (?foo=bar) para el router
        $this->url    = (string) (parse_url($currentUrl, PHP_URL_PATH) ?: '/');
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Method Spoofing: Permite simular peticiones PUT/PATCH/DELETE desde 
        // formularios HTML tradicionales usando un input oculto name="_method"
        // o mediante el header X-HTTP-Method-Override.
        if ($this->method === 'POST') {
            if (isset($_POST['_method'])) {
                $this->method = strtoupper($_POST['_method']);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $this->method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }

        $this->query  = $_GET;
        $this->files  = $_FILES;

        // El orden es vital: parseBody() depende de leer el Content-Type
        // que se extrae en parseHeaders().
        $this->headers = $this->parseHeaders();
        $this->body    = $this->parseBody();
    }

    /**
     * Obtiene el valor de un header de forma case-insensitive.
     */
    public function getHeader(string $name): ?string {
        $key = strtolower($name);
        return $this->headers[$key] ??  null;
    }

    /**
     * Negociación de contenido: Determina si el cliente espera una respuesta JSON.
     * Útil para que el ErrorHandler sepa si devolver una vista HTML o un JSON en caso de fallo.
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
     * Método de conveniencia para obtener un valor del payload (body) 
     * o de la query string (GET), con un valor por defecto (fallback).
     */
    public function input(string $key, mixed $default = null): mixed {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Normaliza los headers recibidos por el servidor web (Apache/Nginx).
     * PHP expone los headers en $_SERVER con el prefijo HTTP_ y en MAYÚSCULAS.
     */
    private function parseHeaders(): array 
    {
        $headers = [];
        foreach($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }

            // Excepciones: Content-Type y Content-Length son expuestos por PHP 
            // sin el prefijo HTTP_ en $_SERVER.
            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    /**
     * Intercepta peticiones entrantes tipo application/json y decodifica el payload.
     * Si no es JSON, asume que es form-data estándar y retorna $_POST.
     * 
     * @throws \InvalidArgumentException Si el payload declara ser JSON pero está malformado.
     */
    private function parseBody(): array 
    {
        $contentType = $this->getHeader('content-type') ?? '';

        if(str_contains($contentType, 'application/json')) {
            // php://input lee el stream raw del body de la petición
            $rawBody = file_get_contents('php://input');
            $decoded = json_decode($rawBody, true);

            if(json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('JSON malformado: ' . json_last_error_msg());
            }

            return $decoded ?? [];
        }

        return $_POST;
    }
}