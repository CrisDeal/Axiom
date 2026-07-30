<?php
declare(strict_types=1);

namespace Axiom\Providers;

use Axiom\Config\ConfigRepository;
use Axiom\DI\Container;
use Axiom\Contracts\Providers\ServiceProviderInterface;
use Axiom\Http\Router\Request;
use Axiom\Http\Router\Response;
use Axiom\Http\Router\MainRouter;
use Axiom\Exceptions\ErrorHandler;

/**
 * RoutingServiceProvider
 *
 * Registra en el contenedor todos los servicios relacionados
 * con el ciclo de vida de una petición HTTP:
 * Request, Response, ErrorHandler y Router.
 *
 * Todos se registran como singleton — existe una sola instancia
 * de cada uno durante el ciclo de vida de la petición, garantizando
 * que el estado (params, headers, sent flag) sea consistente
 * en toda la aplicación.
 *
 * Uso:
 *   $provider = new RoutingServiceProvider('/ruta/a/views');
 *   $provider->register($container);
 *   $provider->boot($container);
 */
class RoutingServiceProvider implements ServiceProviderInterface 
{
    /**
     * Registra Request, Response, ErrorHandler y Router como singletons.
     *
     * El orden de registro no importa aquí — los singletons se resuelven
     * de forma lazy (solo cuando se llama make() por primera vez).
     */
    public function register(Container $container): void {
        // Request — singleton para que params y headers sean consistentes
        // en todo el ciclo de vida de la petición.
        $container->singleton(Request::class, fn() => new Request());

        // Response — singleton para que el flag $sent sea compartido
        // entre el Router, ErrorHandler y cualquier controlador.
        $container->singleton(
            Response::class, 
            function($c) {
                $config = $c->make(ConfigRepository::class);

                return new Response($config->get('views.path', null));
            }
        );
    
        // ErrorHandler actúa como un escudo final. Necesita el Request actual 
        // para loguear qué URL falló, y el Response para emitir vistas de error (ej: 404.php).
        $container->singleton(
            ErrorHandler::class, 
            fn($c) => new ErrorHandler(
                $c->make(Request::class),
                $c->make(Response::class)
            )
        );

        // Core HTTP Dispatcher.
        // Aunque el Contenedor podría resolver esto vía Reflection (Auto-wiring),
        // declarar explícitamente el Closure es micro-optimización de rendimiento
        // crucial para el router, ya que se ejecuta en el 100% de las peticiones.
        $container->singleton(
            MainRouter::class, 
            fn($c) => new MainRouter(
                $c->make(Request::class),
                $c->make(Response::class),
                $c,
                $c->make(ErrorHandler::class)
            )
        );
    }

    /**
     * Se ejecuta cuando TODOS los Service Providers ya pasaron por register().
     * Aquí es seguro interactuar con los servicios instanciados y aplicar side-effects.
     */
    public function boot(Container $container): void {
        // Tomamos el control del sistema de errores nativo de PHP (set_error_handler, set_exception_handler)
        // y lo delegamos al ErrorHandler de Axiom.
        $container->make(ErrorHandler::class)->register();
    }
}