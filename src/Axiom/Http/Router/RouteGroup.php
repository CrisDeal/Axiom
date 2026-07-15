<?php
namespace Axiom\Http\Router;

/**
 * RouterGroup
 * 
 * Agrupa rutas relacionadas bajo un prefijo URL comun.
 * Inspirado en express.Router() de Express.js
 * 
 * Permite organizar las rutas por modulo en archivos separados.
 * manteniendo el bootstrap principal limpio.
 * 
 * Uso basico:
 *  // routes/users.php
 * 
 *  $router = new RouterGroup('/users');
 *  $router->get('/', UserController::class, 'index');
 *  $router->get('/{id}', UserController::class, 'show');
 *  $router->post('/', UserController::class, 'store')
 *      ->middleware(AuthMiddleware::class);
 *  return $router;
 * 
 * // index.php
 * $app->mount(require 'routes/users.php');
 */
class RouteGroup {

    /** 
     * Rutas acumuladas en este grupo.
     * Estructura: [['method' => 'GET', 'url' => '/users', 'fn' => callable, 'middlewares' => []]]
     * 
     * @var array<int, array>
     */
    private array $routes = [];

    /** Referencia a la ultima ruta para encadenar middleware(). */
    private int $lastIndex = -1;

    /**
     * @param string $prefix Prefijo URL compartido por todas las rutas del grupo.
     *                       Ejemplo: '/users', /api/v1'
     */
    public function __construct(
        private string $prefix
    ) {
        $this->prefix = '/' . trim($prefix, '/');
    }

    /** Registra una ruta GET en el grupo */
    public function get(string $url, calable|array $fn): static {
        return $this->addRoute('GET', $url, $fn);
    }

    /** Registra una ruta POST en el grupo */
    public function post(string $url, calable|array $fn): static {
        return $this->addRoute('POST', $url, $fn);
    }

    /** Registra una ruta PUT en el grupo */
    public function put(string $url, calable|array $fn): static {
        return $this->addRoute('PUT', $url, $fn);
    }

    /** Registra una ruta PATCH en el grupo */
    public function patch(string $url, calable|array $fn): static {
        return $this->addRoute('PATCH', $url, $fn);
    }

    /** Registra una ruta DELETE en el grupo */
    public function delete(string $url, calable|array $fn): static {
        return $this->addRoute('DELETE', $url, $fn);
    }

    /**
     * Asigna middlewares a la ultima ruta registrada.
     * 
     * Ejemplo:
     *  $router->post('/', [UserController:.class, 'store'])
     *      ->middleware(AuthMiddleware::class);
     */
    public function middleware(string ...$middlewares): static{
        $this->routes[$this->lastIndex]['middlewares'] = array_merge(
            $this->routes[$this->lastIndex]['middlewares'],
            $middlewares
        );
        return $this;
    }

    /**
     * Retorna todas las rutas del grupo con el prefijo aplicado.
     * Llamado internamente por Axiom::mount().
     * 
     * @return array <int, array>
     */
    public function getRoutes(): array {
        return array_map(function($route) {
            $url = $this->prefix . '/' . ltrim($route['url'], '/');
            // Normaliza doble slash: /users// -> /users/
            $route['url'] = rtrim(preg_replace('#/+#', '/', $url), '/') ?: '/';
            return $route;
        }, $this->routes);
    }

    /** Retorna el prefijo del grupo. */
    public function getPrefix(): string {
        return $this->prefix;
    }

    /** Acumula una ruta en el grupo. */
    private function addRoute(string $method, string $url, callable|array $fn): static {
        $this->routes[] = [
            'method'      => $method,
            'url'         => $url,
            'fn'          => $fn,
            'middlewares' => []
        ];

        $this->lastIndex = array_key_last($this->routes);
        return $this;
    }
}