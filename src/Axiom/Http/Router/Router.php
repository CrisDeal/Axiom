<?php
declare(strict_types=1);

namespace Axiom\Http\Router;

class Router 
{
    private array $routes = [];
    private int $lastIndex = -1;

    /** 
     * Middlewares que se aplicarán a TODAS las rutas de este grupo.
     * @var array<string>
     */
    private array $groupMiddlewares = [];

    public function __construct(private string $prefix) 
    {
        $this->prefix = '/' . trim($prefix, '/');
    }

    // Métodos HTTP...
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
     * Asigna middlewares a la última ruta registrada.
     */
    public function middleware(string ...$middlewares): static
    {
        if ($this->lastIndex === -1) {
            throw new \LogicException('No se ha definido ninguna ruta para asignar middleware.');
        }

        $this->routes[$this->lastIndex]['middlewares'] = array_merge(
            $this->routes[$this->lastIndex]['middlewares'],
            $middlewares
        );
        return $this;
    }

    /**
     * Novedad: Aplica middlewares a TODAS las rutas del grupo.
     * 
     * Ejemplo:
     *  $router = new RouteGroup('/admin');
     *  $router->globalMiddleware(AuthMiddleware::class);
     */
    public function globalMiddleware(string ...$middlewares): static
    {
        $this->groupMiddlewares = array_merge($this->groupMiddlewares, $middlewares);
        return $this;
    }

    /**
     * Retorna todas las rutas del grupo listas para el Router principal.
     */
    public function getRoutes(): array 
    {
        return array_map(function($route) {
            // 1. Construir y normalizar la URL
            $url = $this->prefix . '/' . ltrim($route['url'], '/');
            $route['url'] = rtrim(preg_replace('#/+#', '/', $url), '/') ?: '/';
            
            // 2. Fusionar middlewares: (Grupo primero, Ruta específica después)
            $route['middlewares'] = array_merge($this->groupMiddlewares, $route['middlewares']);
            
            return $route;
        }, $this->routes);
    }

    public function getPrefix(): string {
        return $this->prefix;
    }

    private function addRoute(string $method, string $url, callable|array $action): static 
    {
        $this->routes[] = [
            'method'      => $method,
            'url'         => $url,
            'action'      => $action, // Cambiado de 'fn' a 'action'
            'middlewares' => []
        ];

        $this->lastIndex = array_key_last($this->routes);
        return $this;
    }
}