<?php
declare(strict_types=1);

namespace Axiom\DI;

use ReflectionClass;
use Exception;
use Closure;

/**
 * Container
 *
 * Contenedor de Inyección de Dependencias (DI) de Axiom.
 * Resuelve dependencias automáticamente usando Reflection de PHP,
 * y permite registrar bindings manuales para interfaces, primitivos
 * y servicios que requieren configuración especial.
 *
 * Modos de registro:
 *   bind()      — registra una factory, se crea una instancia nueva cada vez
 *   singleton() — registra una factory, reutiliza la misma instancia
 *   instance()  — registra un objeto ya construido directamente
 *
 * Uso básico:
 *   $container->singleton(Database::class, fn($c) => new Database($config));
 *   $container->bind(MailerInterface::class, SmtpMailer::class);
 *   $controller = $container->make(UserController::class);
 */
class Container 
{

    /**
     * Factories y valores registrados manualmente.
     * Estructura: ['ClassName' => Closure|object|string]
     *
     * @var array<string, mixed>
     */
    private array $bindings = [];

    /**
     * Instancias singleton ya resueltas.
     * Una vez resuelto, make() retorna siempre la misma instancia.
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Clases en proceso de resolución.
     * Usado para detectar dependencias circulares.
     *
     * @var array<string, bool>
     */
    private array $resolving = [];

    /**
     * Registra una factory para crear una instancia nueva en cada make().
     *
     * Ejemplo:
     *   $container->bind(LoggerInterface::class, FileLogger::class);
     *   $container->bind(Mailer::class, fn($c) => new Mailer($c->make(Config::class)));
     *
     * @param string                $abstract Interfaz o clase a registrar.
     * @param string|object $concrete Clase concreta, objeto, o factory closure.
     */
    public function bind(string $abstract, string|object $concrete): void 
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Registra una factory que se resuelve una sola vez.
     * Todas las llamadas posteriores a make() retornan la misma instancia.
     *
     * Ideal para: conexiones a base de datos, loggers, configuración.
     *
     * Ejemplo:
     *   $container->singleton(Database::class, fn($c) => new Database($config));
     *   $container->singleton(Request::class, fn() => new Request());
     *
     * @param string          $abstract Interfaz o clase a registrar.
     * @param string|Closure $concrete Clase concreta o factory closure.
     */
    public function singleton(string $abstract, string|Closure $concrete): void 
    {
        // Marcamos el binding para que make() sepa que debe cachear la instancia.
        $this->bindings[$abstract] = $concrete;
        $this->instances[$abstract] = null; // reserva el slot
    }

    /**
     * Registra una instancia ya construida como singleton.
     * make() siempre retornará este mismo objeto.
     *
     * Útil para registrar objetos creados en el bootstrap
     * (Request, Response, ErrorHandler) en el contenedor.
     *
     * Ejemplo:
     *   $container->instance(Request::class, $request);
     *   $container->instance(Response::class, $response);
     *
     * @param string $abstract  Interfaz o clase a registrar.
     * @param object $instance  Instancia ya construida.
     */
    public function instance(string $abstract, object $instance): void 
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resuelve y retorna una instancia de la clase o interfaz solicitada.
     *
     * Orden de resolución:
     *   1. Instancia singleton ya resuelta -> retorna directamente
     *   2. Binding manual registrado -> ejecuta la factory o usa la clase concreta
     *   3. Auto-resolución con Reflection -> inspecciona el constructor y resuelve dependencias
     *
     * @param  string $abstract Clase o interfaz a resolver.
     * @return object
     *
     * @throws Exception Si la clase no es instanciable, hay dependencias circulares,
     *                   o un parámetro primitivo no puede resolverse.
     */
    public function make(string $abstract): object 
    {        
        // Singleton ya resuelto — retorna la instancia cacheada.
        if(isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Detección de dependencias circulares.
        if (isset($this->resolving[$abstract])) {
            throw new Exception(
                "Dependencia circular detectada al resolver '$abstract'."
            );
        }

        $this->resolving[$abstract] = true;

        try {
            $instance = $this->resolve($abstract);
        } finally {
            // Siempre limpiamos el flag, incluso si resolve() lanza una excepción.
            unset($this->resolving[$abstract]);
        }

        // Si estaba registrado como singleton (slot reservado con null), cacheamos la instancia recién creada.
        if (array_key_exists($abstract, $this->instances)) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Lógica interna de resolución.
     * Separada de make() para mantener limpio el flujo de singleton y circular check.
     *
     * @throws Exception
     */
    private function resolve(string $abstract): object 
    {
        // Binding manual registrado.
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];

            // Closure — factory ejecutable.
            if ($concrete instanceof Closure) {
                return $concrete($this);
            }

            // String — nombre de clase concreta, se auto-resuelve.
            if (is_string($concrete)) {
                // Si el concreto es el mismo que el abstracto, construimos directamente
                if ($concrete === $abstract) {
                    return $this->build($concrete);
                }

                // Si es distinto, resolvemos normalmente
                return $this->make($concrete);
            }

            // Objeto directo — se retorna tal cual.
            return $concrete;
        }

        // Auto-resolución con Reflection.
        return $this->build($abstract);
    }

    /**
     * Construye una instancia usando Reflection, resolviendo
     * recursivamente todas las dependencias del constructor.
     *
     * Maneja tres tipos de parámetros:
     *   - Clase/interfaz -> se resuelve recursivamente con make()
     *   - Primitivo con valor por defecto -> usa el valor por defecto
     *   - Primitivo sin valor por defecto -> lanza excepción
     *
     * @throws Exception
     */
    private function build(string $abstract): object 
    {
        $reflector = new ReflectionClass($abstract);

        if (!$reflector->isInstantiable()) {
            throw new Exception(
                "La clase '$abstract' no es instanciable. " .
                "¿Olvidaste registrar un binding para esta interfaz?"
            );
        }

        $constructor = $reflector->getConstructor();

        // Sin constructor — se instancia directamente.
        if (!$constructor) {
            return new $abstract();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type && !$type->isBuiltin()) {
                // Dependencia de tipo clase/interfaz — se resuelve recursivamente.
                $dependencies[] = $this->make($type->getName());

            } elseif ($param->isOptional()) {
                // Parámetro primitivo con valor por defecto — se usa el default.
                $dependencies[] = $param->getDefaultValue();

            } else {
                // Parámetro primitivo sin valor por defecto — no se puede auto-resolver.
                throw new Exception(
                    "No se puede resolver el parámetro '{$param->getName()}' " .
                    "de tipo primitivo en '$abstract'. " .
                    "Registra un binding manual con bind() o singleton()."
                );
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}