<?php
declare(strict_types=1);

namespace Axiom;

use Axiom\Config\ConfigRepository;
use Axiom\DI\Container;
use Axiom\Http\Router\MainRouter;
use Axiom\Http\Router\Router;
use Axiom\Providers\DatabaseServiceProvider;
use Axiom\Providers\FetchServiceProvider;
use Axiom\Providers\RoutingServiceProvider;
use Axiom\Contracts\Providers\ServiceProviderInterface;

/**
 * Axiom Application (Kernel)
 * 
 * El núcleo del framework. Actúa como fachada (Facade) para registrar rutas, 
 * configurar el contenedor de Inyección de Dependencias (DI) y orquestar 
 * el ciclo de vida (Bootstrapping) de la petición HTTP.
 */
class Axiom {

    // ====================================================================================
    // CORE & CONFIGURACIÓN
    // ====================================================================================

    public readonly Container $container;
    private ?string $configPath = null;

    /** @var ServiceProviderInterface[] Providers registrados por el usuario. */
    private array $providers = [];

    /** @var MainRouter Instancia resuelta durante la fase de boot. */
    public MainRouter $router;

    /** 
     * @var array Rutas en memoria temporal.
     * Implementa 'Deferred Execution': Las rutas se guardan aquí hasta que el 
     * Router real esté instanciado y configurado con sus dependencias.
     */
    private array $pendingRoutes = [];

    /** Índice de la última ruta agregada para permitir chaining de middlewares. */
    private int $lastPendingIndex = -1;


    public function __construct() {
        $this->container = new Container();
    }

    /**
     * Define la ruta al directorio de configuraciones.
     * 
     * Debe llamarse antes de run().
     */
    public function loadConfig(string $path): static
    {
        $this->configPath = $path;
        return $this;
    }

    /**
     * Registra un Service Provider personalizado.
     */
    public function addProvider(ServiceProviderInterface $provider): static {
        $this->providers[] = $provider;
        return $this;
    }


    // ====================================================================================
    // PROXY DE ENRUTAMIENTO (DEFERRED)
    // ====================================================================================

    public function get(string $url, callable|array $action): static {
        return $this->addPendingRoute('GET', $url, $action);
    }

    public function post(string $url, callable|array $action): static {
        return $this->addPendingRoute('POST', $url, $action);
    }

    public function put(string $url, callable|array $action): static {
        return $this->addPendingRoute('PUT', $url, $action);
    }

    public function patch(string $url, callable|array $action): static {
        return $this->addPendingRoute('PATCH', $url, $action);
    }

    public function delete(string $url, callable|array $action): static {
        return $this->addPendingRoute('DELETE', $url, $action);
    }

    /**
     * Acumula una ruta como pendiente hasta que boot() la registre.
     */
    private function addPendingRoute(string $method, string $url, callable|array $action): static {
        $this->pendingRoutes[] = [
            'method'      => $method,
            'url'         => $url,
            'action'          => $action,
            'middlewares' => []
        ];

        $this->lastPendingIndex = array_key_last($this->pendingRoutes);
        return $this;
    }
    
    /**
     * Asigna middlewares a la última ruta registrada de forma encadenada.
     */
    public function middleware(string ...$middlewares): static {
        if ($this->lastPendingIndex === -1) {
            throw new \LogicException('Application: No hay una ruta previa para asignar el middleware.');
        }

        $this->pendingRoutes[$this->lastPendingIndex]['middlewares'] = array_merge(
            $this->pendingRoutes[$this->lastPendingIndex]['middlewares'],
            $middlewares
        );
        return $this;
    }

    /**
     * Extrae las rutas de un RouteGroup (Router) y las pasa a la cola de pendientes.
     */
    public function mount(Router $group): void 
    {
        foreach ($group->getRoutes() as $route) {
            $this->pendingRoutes[] = $route;
            $this->lastPendingIndex = array_key_last($this->pendingRoutes);
        }

        // BUGFIX: Reseteamos el índice para evitar que un ->middleware() encadenado 
        // a la app afecte accidentalmente solo a la última ruta de este grupo.
        $this->lastPendingIndex = -1;
    }


    // ====================================================================================
    // CICLO DE VIDA (BOOTSTRAPPING)
    // ====================================================================================

    /** 
     * Inicializar la applicacion y despacha la peticion HTTP.
     * Debe llamarse al final, despues de registrar rutas y providers.
     */
    public function run(): void 
    {
        $this->boot();
        $this->router->verifyRoutes();
    }

    /**
     * Ejecuta el ciclo registrer -> boot de todos los providers,
     * inicializa el Router y registra todas las rutas acumuladas.
     */
    private function boot(): void 
    {        
        $this->loadConfiguration();
        $this->bootErrorHandler();
        $this->bootProviders();
        $this->bootRouter();
    }

    /**
    * Carga los archivos de configuración del proyecto consumidor
    * y registra el ConfigRepository en el contenedor.
    */
    private function loadConfiguration(): void
    {
        $config = new ConfigRepository();

        if ($this->configPath) {
            $config->load($this->configPath);
        }

        $this->container->instance(ConfigRepository::class, $config);
    }

    /**
     * Se enciende primero para que cualquier Excepción lanzada por los siguientes 
     * Providers sea capturada y renderizada amigablemente por Axiom, no por PHP nativo.
     */
    private function bootErrorHandler(): void
    {
        $routingProvider = new RoutingServiceProvider();
        $routingProvider->register($this->container);
        $routingProvider->boot($this->container);
    }

    /**
     * Ejecuta el ciclo de 2 Fases (Register -> Boot) de los 
     * providers de axiom garantizando que todas las dependencias 
     * estén listas antes de usarse.
     */
    private function bootProviders(): void
    {
        $providers = [
            new DatabaseServiceProvider(),
            new FetchServiceProvider(),
            ...$this->providers
        ];

        // Fase 1: Enseñar al contenedor cómo construir todo
        foreach ($providers as $provider) {
            $provider->register($this->container);
        }

        // Fase 2: Ejecutar lógica de inicio seguro
        foreach ($providers as $provider) {
            $provider->boot($this->container);
        }
    }

    /**
     * Resuelve el Router del contenedor y vacía la cola temporal de rutas (Deferred Routing).
     */
    private function bootRouter(): void
    {
        $this->router = $this->container->make(MainRouter::class);

        foreach ($this->pendingRoutes as $route) {
            $this->router
                ->addRoute($route['method'], $route['url'], $route['action'])
                ->middleware(...$route['middlewares']);
        }
    }
}