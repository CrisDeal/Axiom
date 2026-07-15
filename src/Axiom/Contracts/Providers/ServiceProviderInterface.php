<?php
namespace Axiom\Contracts\Providers;

use Axiom\DI\Container;

/**
 * ServiceProviderInterface
 *
 * Contrato que deben implementar todos los providers de Axiom.
 *
 * Un provider es la unidad de organización de servicios en el framework —
 * agrupa el registro e inicialización de un conjunto de clases relacionadas
 * en el contenedor DI.
 *
 * El ciclo de vida de un provider tiene dos fases separadas e importantes:
 *
 *   1. register() — todos los providers registran sus bindings primero.
 *      En esta fase solo se declara qué existe en el contenedor.
 *      No se debe resolver ni usar ningún servicio aquí, porque otros
 *      providers aún no han registrado los suyos.
 *
 *   2. boot() — se ejecuta después de que TODOS los providers han
 *      completado su register(). En esta fase es seguro resolver
 *      servicios del contenedor porque todo está disponible.
 *
 * Ejemplo de implementación:
 *
 *   class MailServiceProvider implements ServiceProviderInterface {
 *       public function register(Container $container): void {
 *           $container->singleton(Mailer::class, fn() => new SmtpMailer());
 *       }
 *
 *       public function boot(Container $container): void {
 *           $container->make(Mailer::class)->connect();
 *       }
 *   }
 */
interface ServiceProviderInterface {

    /**
     * Registra los bindings del provider en el contenedor DI.
     * Se ejecuta antes que boot() — no resolver servicios aquí.
     *
     * @param Container $container Contenedor DI de la aplicación.
     */
    public function register(Container $container): void;

    /**
     * Inicializa los servicios del provider.
     * Se ejecuta después de que todos los providers han completado register(),
     * por lo que es seguro resolver cualquier servicio del contenedor.
     *
     * @param Container $container Contenedor DI de la aplicación.
     */
    public function boot(Container $container): void;
}