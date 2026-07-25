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
 * Application Axiom
 * 
 * Punto de entrada del framework Axiom.
 * Orquesta el contenedor DI, los providers y el ciclo de vida de la peticion HTTP.
 * 
 */
class Axiom {

    // ====================================================================================
    // CONFIGURACION
    // ====================================================================================

    /** Contenedor DI compartido por toda la applicacion. */
    public readonly Container $container;

    private ?string $configPath = null;

    /** Providers registrados por el usuario. */
    private array $providers = [];

    /** Router interno - se resuelve del contenedor en boot() */
    public MainRouter $router;

    /** 
     * Rutas pendientes de registrar.
     * Se acumulan antes de boot() y se registran cuando el router este listo.
     * Estructura: [['method' => 'GET', 'url' => '/ping', 'action' => callable, 'middlewares' => []]]
     * 
     * @var array<int, array>
     */
    private array $pendingRoutes = [];

    /** Referencia a la ultima ruta pendiente para encadenamiento de middlewares. */
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
     * Agrega un provider a la applicacion.
     * Debe llamarse antes de run().
     * 
     * Ejemplo: 
     *  $app->addProvider(new FetchServiceProvider());
     */
    public function addProvider(ServiceProviderInterface $provider): static {
        $this->providers[] = $provider;
        return $this;
    }


    // ====================================================================================
    // ROUTER PRINCIPAL
    // ====================================================================================

    /** Registra una ruta GET. */
    public function get(string $url, callable|array $action): static {
        return $this->addPendingRoute('GET', $url, $action);
    }

    /** Registra una ruta POST. */
    public function post(string $url, callable|array $action): static {
        return $this->addPendingRoute('POST', $url, $action);
    }

    /** Registra una ruta PUT. */
    public function put(string $url, callable|array $action): static {
        return $this->addPendingRoute('PUT', $url, $action);
    }

    /** Registra una ruta PATCH. */
    public function patch(string $url, callable|array $action): static {
        return $this->addPendingRoute('PATCH', $url, $action);
    }

    /** Registra una ruta DELETE. */
    public function delete(string $url, callable|array $action): static {
        return $this->addPendingRoute('DELETE', $url, $action);
    }

    /**
     * Asigna middlewares a la ultima ruta registrada.
     * Funciona igual antes y despues de boot().
     * 
     * Ejemplo: 
     *  $app->get('/admin', [AdminController::class, 'index'])
     *      ->middleware(AuthMiddleware::class);
     */
    public function middleware(string ...$middlewares): static {
        $this->pendingRoutes[$this->lastPendingIndex]['middlewares'] = array_merge(
            $this->pendingRoutes[$this->lastPendingIndex]['middlewares'],
            $middlewares
        );
        return $this;
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
     * Monta un grupo de rutas en la application.
     * Las rutas del grupo se registran con su prefijo aplicado.
     * 
     * Ejemplo: 
     *  $app->mount(require 'routes/users.php');
     *  $app->mount(require 'routes/products.php');
     * 
     * @param Router $group Grupo de rutas a montar.
     */
    public function mount(Router $group): void {
        foreach ($group->getRoutes() as $route) {
            $this->pendingRoutes[] = $route;
            $this->lastPendingIndex = array_key_last($this->pendingRoutes);
        }
    }


    /** 
     * Inicializar la applicacion y despacha la peticion HTTP.
     * Debe llamarse al final, despues de registrar rutas y providers.
     */
    public function run(): void {
        $this->boot();
        // \Axiom\Support\Debug::dd($this->container);
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
     * Inicializa el RoutingServiceProvider y registra el ErrorHandler en PHP.
     * Se ejecuta antes que cualquier otro provider para capturar
     * errores de configuración y arranque.
     */
    private function bootErrorHandler(): void
    {
        $routingProvider = new RoutingServiceProvider();
        $routingProvider->register($this->container);
        $routingProvider->boot($this->container);
    }

    /**
     * Ejecuta el ciclo register → boot de todos los providers restantes.
     */
    private function bootProviders(): void
    {
        $providers = [
            new DatabaseServiceProvider(),
            new FetchServiceProvider(),
            ...$this->providers
        ];

        foreach ($providers as $provider) {
            $provider->register($this->container);
        }

        foreach ($providers as $provider) {
            $provider->boot($this->container);
        }
    }

    /**
     * Resuelve el Router del contenedor y registra todas las rutas acumuladas.
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