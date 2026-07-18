<?php
declare(strict_types=1);

namespace Axiom\Http\Router;

use Axiom\DI\Container;
use Axiom\Exceptions\ErrorHandler;
use Axiom\Exceptions\MethodNotAllowedException;
use Axiom\Exceptions\NotFoundException;

/**
 * Router
 *
 * Registra rutas HTTP y despacha las peticiones entrantes al
 * controlador o callable correspondiente, pasándolas primero
 * por el pipeline de middlewares configurado.
 *
 * Uso básico:
 *   $router->get('/', function($req, $res) {...});
 *   $router->get('/users', [UserController::class, 'index']);
 *   $router->post('/users', [UserController::class, 'store'])
 *      ->middleware(AuthMiddleware::class);
 * 
 *   $router->verifyRoutes();
 */
class Router 
{
    private const FOUND = 'FOUND';
    private const NOT_FOUND = 'NOT_FOUND';
    private const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';

    /**
     * Rutas registradas indexadas por método HTTP y URL.
     * Estructura: 
     *  [
     *      'GET' => [
     *          '/url' => [
     *              'action' => [UrlController::class, 'index'], 
     *              'middlewares' => [...]
     *          ]
     *      ]
     *  ]
     *
     * @var array<string, array<string, array>>
     */
    private array $routes = [];

    /**
     * Referencia a la última ruta registrada.
     * Permite encadenar ->middleware() después de get(), post(), etc.
     *
     * @var array{method: string, url: string}
     */
    private array $lastRoute = [];

    /**
     * @param Request      $request      Petición HTTP entrante.
     * @param Response     $response     Respuesta HTTP saliente.
     * @param Container    $container    Contenedor DI para resolver controladores y middlewares.
     * @param ErrorHandler $errorHandler Manejador de errores compartido con el bootstrap.
     */
    public function __construct(
        private Request $request,
        private Response $response,
        private Container $container,
        private ErrorHandler $errorHandler
    ) {}

    /**
     * Registra una ruta en el mapa interno del Router.
     *
     * @param string         $method Método HTTP en mayúsculas. Ejemplo: 'GET'
     * @param string         $url    Ruta. Soporta parámetros dinámicos: '/users/{id}'
     * @param callable|array $fn     Callable o [ControllerClass::class, 'method']
     * @return self          $this   Permite encadenamiento con ->middleware()
     */
    public function addRoute(string $method, string $url, callable|array $fn) : self 
    {
        $this->routes[$method][$url] = [
            'action'      => $fn,
            'middlewares' => []
        ];

        $this->lastRoute = ['method' => $method, 'url' => $url];

        return $this;
    }

    /** Registra una ruta GET. */
    public function get(string $url, callable|array $fn) : self {
        return $this->addRoute('GET', $url, $fn);
    }

    /** Registra una ruta POST. */
    public function post(string $url, callable|array $fn) : self {
        return $this->addRoute('POST', $url, $fn);
    }

    /** Registra una ruta PUT. */
    public function put(string $url, callable|array $fn) : self {
        return $this->addRoute('PUT', $url, $fn);
    }

    /** Registra una ruta PATCH. */
    public function patch(string $url, callable|array $fn) : self {
        return $this->addRoute('PATCH', $url, $fn);
    }

    /** Registra una ruta DELETE. */
    public function delete(string $url, callable|array $fn) : self {
        return $this->addRoute('DELETE', $url, $fn);
    }

    /**
     * Asigna uno o más middlewares a la última ruta registrada.
     * Debe llamarse inmediatamente después de get(), post(), etc.
     *
     * Ejemplo:
     *   $router->get('/admin', [AdminController::class, 'index'])
     *          ->middleware(AuthMiddleware::class, RateLimitMiddleware::class);
     *
     * @param  string ...$middlewares Clases de middleware a aplicar en orden.
     * @throws \LogicException Si se llama sin haber registrado una ruta antes.
     */
    public function middleware(string ...$middlewares) : self 
    {
        if(empty($this->lastRoute)) {
            throw new \LogicException('No se ha definido ninguna ruta aún para asignar middleware.');
        }

        $method = $this->lastRoute['method'];
        $url    = $this->lastRoute['url'];

        foreach($middlewares as $middleware) {
            $this->routes[$method][$url]['middlewares'][] = $middleware;
        }

        return $this;
    }

    /**
     * Punto de entrada principal del ciclo de vida de la petición.
     * Debe llamarse al final del bootstrap, después de registrar todas las rutas.
     *
     * Hace match de la petición entrante contra las rutas registradas,
     * ejecuta el pipeline de middlewares y despacha al controlador.
     * Cualquier excepción no capturada es delegada al ErrorHandler.
     */
    // public function verifyRoutes(): void 
    // {
    //     try {
    //         $routeData = $this->matchRoute($this->request);

    //         if(!$routeData) {
    //             throw new NotFoundException("La ruta '{$this->request->url}' no existe.");
    //         }

    //         $this->dispatch($routeData);

    //         if(!$this->response->hasBeenSent()) {
    //             throw new \LogicException('No se envió ninguna respuesta para esta ruta.');
    //         }
    //     } catch(\Throwable $e) {
    //         $this->handleException($e);
    //     }
    // }
    public function verifyRoutes(): void
    {
        try {

            $result = $this->matchRoute($this->request);

            switch($result['status']) {

                case self::FOUND:
                    $this->dispatch($result['route']);
                    break;

                case self::METHOD_NOT_ALLOWED:
                    throw new MethodNotAllowedException(
                        $result['allowed']
                    );

                case self::NOT_FOUND:
                    throw new NotFoundException(
                        "La ruta '{$this->request->url}' no existe."
                    );
            }

            if(!$this->response->hasBeenSent()) {
                throw new \LogicException(
                    'No se envió ninguna respuesta para esta ruta.'
                );
            }

        } catch(\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Busca la ruta que coincide con el método y URL de la petición.
     *
     * Primero intenta match estático (más rápido).
     * Si no encuentra, itera sobre las rutas dinámicas usando regex.
     * Los parámetros dinámicos encontrados se populan en $request->params.
     *
     * Ejemplo de ruta dinámica: '/users/{id}' matchea '/users/42'
     * y popula $request->params['id'] = '42'.
     *
     * @return array|null Datos de la ruta encontrada, o null si no hay match.
     */
    // private function matchRoute(Request $req): ?array 
    // {
    //     $routes = $this->routes[$req->method] ?? [];

    //     // Match estático — O(1), se evalúa primero por rendimiento.
    //     if(isset($routes[$req->url])) {
    //         return $routes[$req->url];
    //     }

    //     // Match dinámico — convierte {param} en grupo regex nombrado.
    //     foreach($routes as $route => $data) {
    //         $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[^/]+)', $route);
    //         $pattern = '#^' . $pattern . '$#';

    //         if(preg_match($pattern, $req->url, $matches)) {
    //             foreach($matches as $key => $value) {
    //                 if(is_string($key)) {
    //                     $req->params[$key] = $value;
    //                 }
    //             }
    //             return $data;
    //         }
    //     }

    //     return null;
    // }

    private function matchRoute(Request $req): array
    {
        $allowedMethods = [];

        foreach($this->routes as $method => $routes) {

            // Match estático
            if(isset($routes[$req->url])) {

                if($method === $req->method) {
                    return [
                        'status' => self::FOUND,
                        'route'  => $routes[$req->url]
                    ];
                }

                $allowedMethods[] = $method;
            }

            // Match dinámico
            foreach($routes as $route => $data) {

                $pattern = preg_replace(
                    '/\{([a-zA-Z0-9_]+)\}/',
                    '(?P<\1>[^/]+)',
                    $route
                );

                $pattern = '#^' . $pattern . '$#';

                if(!preg_match($pattern, $req->url, $matches)) {
                    continue;
                }

                if($method !== $req->method) {
                    $allowedMethods[] = $method;
                    continue;
                }

                foreach($matches as $key => $value) {
                    if(is_string($key)) {
                        $req->params[$key] = $value;
                    }
                }

                return [
                    'status' => self::FOUND,
                    'route'  => $data
                ];
            }
        }

        if(!empty($allowedMethods)) {
            return [
                'status'  => self::METHOD_NOT_ALLOWED,
                'allowed' => array_unique($allowedMethods)
            ];
        }

        return [
            'status' => self::NOT_FOUND
        ];
    }

    /**
     * Ejecuta el pipeline de middlewares y despacha al controlador.
     *
     * El pipeline envuelve el controlador en capas de middleware —
     * cada middleware puede actuar antes y después del siguiente.
     * El controlador es siempre el núcleo (última capa).
     *
     * @param array $routeData Datos de la ruta: action y middlewares.
     */
    private function dispatch(array $routeData): void 
    {
        $action      = $routeData['action'];
        $middlewares = $routeData['middlewares'];

        // El controlador es el núcleo del pipeline.
        $core = function($req, $res) use ($action) {
            if(is_array($action)) {
                [$controllerClass, $method] = $action;
                $controller = $this->container->make($controllerClass);
                return $controller->$method($req, $res);
            }

            return $action($req, $res);
        };

        $pipeline = $this->buildMiddlewarePipeline($middlewares, $core);
        $pipeline($this->request, $this->response);
    }

    /**
     * Construye el pipeline de middlewares usando reducción funcional.
     *
     * Los middlewares se aplican en el orden en que fueron registrados —
     * array_reverse garantiza que el primero registrado sea el primero en ejecutarse.
     *
     * Cada middleware recibe ($req, $res, $next) donde $next ejecuta
     * el siguiente middleware o el controlador si es la última capa.
     *
     * @param  array    $middlewares Clases de middleware a encadenar.
     * @param  callable $core        Controlador final del pipeline.
     * @return callable              Pipeline completo listo para ejecutar.
     */
    private function buildMiddlewarePipeline(array $middlewares, callable $core): callable 
    {
        return array_reduce(
            array_reverse($middlewares),
            function($next, $middlewareClass) {
                return function($req, $res) use ($next, $middlewareClass) {
                    $middleware = $this->container->make($middlewareClass);
                    // return $middleware->handle($req, $res, fn() => $next($req, $res));
                    // return $middleware->handle($req, $res, fn($req, $res) => $next($req, $res));
                    return $middleware->handle($req, $res, function() use ($next, $req, $res) {
                        return $next($req, $res);
                    });
                };
            },
            $core
        );
    }

    /**
     * Delega una excepción al ErrorHandler compartido.
     *
     * Si la respuesta ya fue enviada, el ErrorHandler lo detecta
     * internamente y solo registra el error sin intentar enviar nada.
     *
     * @param \Throwable $e Excepción a manejar.
     */
    private function handleException(\Throwable $e): void {
        $this->errorHandler->handle($e);
    }
}





// El siguiente nivel sería:

// ✅ Request inmutable
// ✅ Kernel / Application
// ✅ Service Providers
// ✅ Middleware globales
// ✅ Route groups
// No existe diferenciación entre 404 y 405
// Las regex se compilan en cada request (cachear)

// . No hay constraints
// Actualmente:
// /users/{id}

// acepta:
// /users/abc
// /users/123
// /users/!!!

// Más adelante podrías soportar:
// /users/{id:\d+}

// para generar:
// (?P<id>\d+)