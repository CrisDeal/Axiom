<?php
declare(strict_types=1);

namespace Axiom\Http\Router;

/**
 * Route Collector / Route Group
 *
 * Esta clase actúa como un constructor (Builder) para agrupar rutas bajo un mismo prefijo
 * y compartir middlewares. NO despacha las peticiones. Su propósito es recopilar 
 * la configuración de las rutas para luego ser "montadas" en el MainRouter.
 *
 * Ejemplo de uso:
 *  $api = new Router('/api/v1');
 *  $api->globalMiddleware(ApiAuthMiddleware::class);
 *  $api->get('/users', [UserController::class, 'index'])->middleware(RoleMiddleware::class);
 *  
 *  $mainRouter->mount($api);
 */
class Router 
{
    /**
     * @var array Lista de rutas configuradas en este grupo.
     */
    private array $routes = [];

    /**
     * @var int Puntero al índice de la última ruta registrada.
     * Es crucial para permitir la interfaz fluida en la asignación de middlewares:
     * ->get(...)->middleware(...)
     */
    private int $lastIndex = -1;

    /**
     * @var array<string> Middlewares que se aplicarán a TODAS las rutas de este grupo.
     */
    private array $globalMiddlewares = [];

    /**
     * @param string $prefix Prefijo base para todas las rutas del grupo (ej: '/admin').
     */
    public function __construct(private string $prefix = '/') 
    {
        // Aseguramos que el prefijo siempre comience con '/' y no tenga trailing slash
        $this->prefix = '/' . trim($prefix, '/');
    }

    /**
     * Registra una ruta GET.
     * 
     * @return static Permite el encadenamiento de métodos (ej: ->middleware())
     */
    public function get(string $url, callable|array $action): static {
        return $this->addRoute('GET', $url, $action);
    }

    public function post(string $url, callable|array $action): static {
        return $this->addRoute('POST', $url, $action);
    }

    public function put(string $url, callable|array $action): static {
        return $this->addRoute('PUT', $url, $action);
    }

    public function patch(string $url, callable|array $action): static {
        return $this->addRoute('PATCH', $url, $action);
    }

    public function delete(string $url, callable|array $action): static {
        return $this->addRoute('DELETE', $url, $action);
    }

    /**
     * Registra internamente la ruta y actualiza el puntero de estado.
     * 
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $url URL relativa al prefijo del grupo
     * @param callable|array $action Controlador o función anónima
     */
    private function addRoute(string $method, string $url, callable|array $action): static 
    {
        $this->routes[] = [
            'method'      => $method,
            'url'         => $url,
            'action'      => $action,
            'middlewares' => []
        ];

        // Actualizamos el puntero para que ->middleware() sepa a qué ruta afectar
        $this->lastIndex = array_key_last($this->routes);
        return $this;
    }

    /**
     * Asigna uno o más middlewares a la ÚLTIMA ruta registrada.
     * 
     * ATENCIÓN: Este método depende del estado interno ($lastIndex). 
     * Debe llamarse inmediatamente después de definir un endpoint.
     * 
     * @param string ...$middlewares FQCN (Fully Qualified Class Names) de los middlewares.
     * @throws \LogicException Si se llama sin haber registrado una ruta previa.
     */
    public function middleware(string ...$middlewares): static
    {
        if ($this->lastIndex === -1) {
            throw new \LogicException('Router: No se ha definido ninguna ruta aún para asignar middleware.');
        }

        $this->routes[$this->lastIndex]['middlewares'] = array_merge(
            $this->routes[$this->lastIndex]['middlewares'],
            $middlewares
        );
        return $this;
    }

    /**
     * Asigna middlewares a nivel de grupo. Afectarán a todas las rutas definidas aquí.
     * 
     * @param string ...$middlewares FQCN de los middlewares compartidos.
     */
    public function globalMiddleware(string ...$middlewares): static
    {
        $this->globalMiddlewares = array_merge($this->globalMiddlewares, $middlewares);
        return $this;
    }

    /**
     * Compila y devuelve todas las rutas del grupo listas para el MainRouter.
     * Resuelve los prefijos y fusiona los middlewares en el orden correcto.
     * 
     * @return array
     */
    public function getRoutes(): array 
    {
        return array_map(function($route) {
            // Construir la URL completa: Prefijo + Ruta
            $url = $this->prefix . '/' . ltrim($route['url'], '/');

            // Normalización de seguridad: 
            // - Reemplaza múltiples slashes accidentales (//) por uno solo (/) usando regex.
            // - Remueve el trailing slash al final, a menos que la URL resultante sea exactamente '/'.
            $route['url'] = rtrim(preg_replace('#/+#', '/', $url), '/') ?: '/';
            
            // Fusión de Middlewares:
            // El orden es vital. Los middlewares del grupo ($this->globalMiddlewares)
            // deben ejecutarse ANTES que los middlewares específicos de la ruta ($route['middlewares']).
            $route['middlewares'] = array_merge($this->globalMiddlewares, $route['middlewares']);
            
            return $route;
        }, $this->routes);
    }

    /**
     * Obtiene el prefijo actual del grupo.
     */
    public function getPrefix(): string {
        return $this->prefix;
    }
}