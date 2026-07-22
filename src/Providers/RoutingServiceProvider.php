<?php
declare(strict_types=1);

namespace Axiom\Providers;

use Axiom\Config\ConfigRepository;
use Axiom\DI\Container;
use Axiom\Contracts\Providers\ServiceProviderInterface;
use Axiom\Http\Router\Request;
use Axiom\Http\Router\Response;
use Axiom\Http\Router\Router;
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
    
        // ErrorHandler — singleton que comparte las mismas instancias
        // de Request y Response que usa el Router.
        $container->singleton(
            ErrorHandler::class, 
            fn($c) => new ErrorHandler(
                $c->make(Request::class),
                $c->make(Response::class)
            )
        );

        // Router — recibe todas sus dependencias del contenedor.
        $container->singleton(
            Router::class, 
            fn($c) => new Router(
                $c->make(Request::class),
                $c->make(Response::class),
                $c,
                $c->make(ErrorHandler::class)
            )
        );
    }

    /**
     * Registra el ErrorHandler en PHP una vez que todos los
     * servicios están registrados en el contenedor.
     *
     * boot() se ejecuta después de register() — en este punto
     * el ErrorHandler ya puede resolverse correctamente.
     */
    public function boot(Container $container): void {
        // Boot logic for the routing provider
        $container->make(ErrorHandler::class)->register();
    }
}