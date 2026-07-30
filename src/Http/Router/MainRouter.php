<?php
declare(strict_types=1);

namespace Axiom\Http\Router;

use Axiom\DI\Container;
use Axiom\Exceptions\ErrorHandler;
use Axiom\Exceptions\MethodNotAllowedException;
use Axiom\Exceptions\NotFoundException;

/**
 * MainRouter (Despachador Frontal)
 *
 * Es el motor principal del ciclo de vida de la petición HTTP en Axiom.
 * A diferencia del Router de grupos (que solo colecciona), esta clase:
 * 1. Mantiene el registro final (mapa) de todas las rutas de la aplicación.
 * 2. Evalúa la petición entrante (matcheo estático y dinámico).
 * 3. Ensambla y ejecuta el pipeline de middlewares (patrón cebolla).
 * 4. Resuelve e invoca el controlador final usando Inyección de Dependencias.
 */
class MainRouter 
{
    // Estados internos para la evaluación de la ruta.
    private const FOUND = 'FOUND';
    private const NOT_FOUND = 'NOT_FOUND';
    private const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';

    /**
     * @var array<string, string> Diccionario de alias para restricciones regex en rutas dinámicas.
     * Permite escribir {id:int} en lugar de {id:\d+}.
     */
    private array $patterns = [
        'int'    => '\d+',             // Solo números
        'string' => '[a-zA-Z]+',       // Solo letras
        'alnum'  => '[a-zA-Z0-9]+',    // Letras y números (alfanumérico)
        'path'   => '(/.*)?',          // Cualquier ruta anidada después del slash
    ];

    /**
     * @var array Mapa multidimensional de rutas registradas.
     * Estructura optimizada para búsqueda rápida por método HTTP:
     * [
     *     'GET' => [
     *         '/users' => [
     *             'action'      => [UserController::class, 'index'],
     *             'middlewares' => [AuthMiddleware::class],
     *             'is_dynamic'  => false,
     *             'pattern'     => null
     *         ]
     *     ]
     * ]
     */
    private array $routes = [];

    /**
     * @var array Puntero de estado para encadenamiento fluido (->middleware()).
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

    public function get(string $url, callable|array $fn) : self {
        return $this->addRoute('GET', $url, $fn);
    }

    public function post(string $url, callable|array $fn) : self {
        return $this->addRoute('POST', $url, $fn);
    }

    public function put(string $url, callable|array $fn) : self {
        return $this->addRoute('PUT', $url, $fn);
    }

    public function patch(string $url, callable|array $fn) : self {
        return $this->addRoute('PATCH', $url, $fn);
    }

    public function delete(string $url, callable|array $fn) : self {
        return $this->addRoute('DELETE', $url, $fn);
    }

    /**
     * Registra una ruta base en el motor.
     * 
     * Optimización clave: Si la ruta tiene parámetros dinámicos ({id}),
     * se pre-compila su expresión regular aquí (durante el bootstrap) 
     * y no durante la iteración de búsqueda (matchRoute), ahorrando CPU.
     */
    public function addRoute(string $method, string $url, callable|array $fn) : self 
    {
        // Normalización: Elimina slashes finales para evitar que /users y /users/ sean vistos como distintos.
        $url = rtrim($url, '/') ?: '/';
        
        $isDynamic = str_contains($url, '{');
        $pattern = null;

        if ($isDynamic) {
            // Convierte {param:constraint} en grupos regex nombrados (?P<param>constraint)
            $regex = preg_replace_callback(
                '/\{([a-zA-Z0-9_]+)(?::([^}]+))?\}/',
                function ($matches) {
                    $name = $matches[1];
                    $constraint = $matches[2] ?? null;
                    
                    if (!$constraint) {
                        $rule = '[^/]+'; // Por defecto: cualquier cosa hasta el próximo slash
                    } else {
                        // Resuelve el alias ('int') o usa la regex manual provista
                        $rule = $this->patterns[$constraint] ?? $constraint;
                    }

                    return "(?P<$name>$rule)";
                },
                $url
            );
            $pattern = '#^' . $regex . '$#';
        }

        $this->routes[$method][$url] = [
            'action'      => $fn,
            'middlewares' => [],
            'is_dynamic'  => $isDynamic,
            'pattern'     => $pattern // Regex pre-compilada, lista para preg_match
        ];

        $this->lastRoute = ['method' => $method, 'url' => $url];

        return $this;
    }

    /**
     * Aplica middlewares a la ÚLTIMA ruta que pasó por addRoute().
     * 
     * @throws \LogicException Si se llama fuera de contexto.
     */
    public function middleware(string ...$middlewares) : self 
    {
        if(empty($this->lastRoute)) {
            throw new \LogicException('Router: No se ha definido ninguna ruta aún para asignar middleware.');
        }

        $method = $this->lastRoute['method'];
        $url    = $this->lastRoute['url'];

        foreach($middlewares as $middleware) {
            $this->routes[$method][$url]['middlewares'][] = $middleware;
        }

        return $this;
    }

    /**
     * Punto de entrada principal (Trigger).
     * Arranca la validación, despacha la petición y asegura que el ciclo
     * se cierre correctamente (Response enviada).
     */
    public function verifyRoutes(): void
    {
        try {
            $result = $this->matchRoute($this->request);

            switch($result['status']) {

                case self::FOUND:
                    $this->dispatch($result['route']);
                    break;

                case self::METHOD_NOT_ALLOWED:
                    // Se encontró la URL, pero el cliente usó un verbo incorrecto (ej: POST en vez de GET)
                    throw new MethodNotAllowedException($result['allowed']);

                case self::NOT_FOUND:
                    throw new NotFoundException("La ruta '{$this->request->url}' no existe.");
            }

            // Guard rails: Asegura que el controlador final realmente haya emitido una respuesta
            if(!$this->response->hasBeenSent()) {
                throw new \LogicException('Router: No se envió ninguna respuesta para esta ruta.');
            }

        } catch(\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Motor de búsqueda de rutas híbrido.
     * 
     * Prioriza la velocidad: primero busca colisiones estáticas directas O(1).
     * Solo si falla, itera sobre rutas dinámicas O(N) ejecutando regex.
     * 
     * @return array Array estructurado con el estado (status) y los datos de la ruta o métodos permitidos.
     */
    private function matchRoute(Request $req): array
    {
        $allowedMethods = [];
        $url = rtrim($req->url, '/') ?: '/';

        foreach($this->routes as $method => $routes) {
            // Match Estático (Súper rápido)
            if(isset($routes[$url])) {
                if($method === $req->method) {
                    return [
                        'status' => self::FOUND,
                        'route'  => $routes[$url]
                    ];
                }
                // Si la URL existe pero el método no coincide, registramos qué método sí era válido
                $allowedMethods[] = $method;
            }

            // Match Dinámico (Regex)
            foreach($routes as $route => $data) {
                if (!$data['is_dynamic']) continue;

                if(!preg_match($data['pattern'], $url, $matches)) {
                    continue;
                }

                if($method !== $req->method) {
                    $allowedMethods[] = $method;
                    continue;
                }

                // Inyección de parámetros extraídos de la URL al Request
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

        // Si recolectamos métodos permitidos, lanzamos 405 en lugar de 404
        if(!empty($allowedMethods)) {
            return [
                'status'  => self::METHOD_NOT_ALLOWED,
                'allowed' => array_unique($allowedMethods)
            ];
        }

        return ['status' => self::NOT_FOUND];
    }

    /**
     * Prepara e inicia la ejecución de la ruta seleccionada.
     * Encapsula el controlador final dentro del pipeline de middlewares.
     */
    private function dispatch(array $routeData): void 
    {
        $action      = $routeData['action'];
        $middlewares = $routeData['middlewares'];

        // Closure del núcleo (El destino final de la petición)
        $core = function($req, $res) use ($action) {
            // Resolución via DI Container si es un array [Controlador, 'método']
            if(is_array($action)) {
                [$controllerClass, $method] = $action;
                $controller = $this->container->make($controllerClass);
                return $controller->$method($req, $res);
            }

            // Ejecución directa si es una función anónima (Closure)
            return $action($req, $res);
        };

        // Ensambla y ejecuta el pipeline de middlewares alrededor del núcleo
        $pipeline = $this->buildMiddlewarePipeline($middlewares, $core);
        $pipeline($this->request, $this->response);
    }

    /**
     * Ensamblador funcional de Middlewares (Patrón Onion / Cebolla).
     * 
     * Convierte un arreglo lineal de clases middleware en una cadena de funciones anidadas.
     * array_reverse asegura que el primer middleware registrado sea la capa más externa,
     * envolviendo a los siguientes hasta llegar a $core.
     * 
     * @return callable Función que arranca toda la cadena.
     */
    private function buildMiddlewarePipeline(array $middlewares, callable $core): callable 
    {
        return array_reduce(
            array_reverse($middlewares),
            function($next, $middlewareClass) {
                return function($req, $res) use ($next, $middlewareClass) {
                    // Lazy Loading: El middleware se instancia justo en el momento de ejecutarse
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
     * Integra un RouteGroup (Router secundario) dentro de este MainRouter.
     * Transfiere todas las rutas compiladas y sus middlewares globales/específicos.
     */
    public function mount(Router $group): self
    {
        foreach ($group->getRoutes() as $route) {
            $this->addRoute($route['method'], $route['url'], $route['action']);
            
            if (!empty($route['middlewares'])) {
                $this->middleware(...$route['middlewares']);
            }
        }

        return $this;
    }

    /**
     * Intercepta excepciones arrojadas durante el pipeline o la resolución de la ruta
     * y las deriva al manejador centralizado para un formato de salida consistente.
     */
    private function handleException(\Throwable $e): void {
        $this->errorHandler->handle($e);
    }
}







// ✅ Request inmutable
// Las regex se compilan en cada request (cachear)
