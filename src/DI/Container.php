<?php
declare(strict_types=1);

namespace Axiom\DI;

use ReflectionClass;
use Exception;
use Closure;
use ReflectionNamedType;

/**
 * Contenedor de Inyección de Dependencias (DI)
 *
 * El "corazón" de la aplicación. Se encarga de instanciar clases automáticamente
 * leyendo sus constructores (Auto-wiring) y resolviendo sus dependencias en cascada.
 *
 * Modos de registro:
 *   - bind():      Crea una instancia completamente nueva cada vez que se solicita.
 *   - singleton(): Ejecuta la factory una vez y devuelve esa misma instancia siempre.
 *   - instance():  Registra un objeto que ya fue instanciado fuera del contenedor.
 *
 * Ejemplo de uso:
 *   $container->singleton(Database::class, fn($c) => new Database($config));
 *   $container->bind(MailerInterface::class, SmtpMailer::class);
 *   $controller = $container->make(UserController::class);
 */
class Container 
{
    /**
     * @var array<string, Closure|object|string> Referencias manuales registradas por el usuario.
     */
    private array $bindings = [];

    /**
     * @var array<string, object|null> Caché de instancias únicas. 
     * Se inicializan en `null` para reservar el espacio cuando se llama a singleton().
     */
    private array $instances = [];

    /**
     * @var array<string, true> Mapa temporal para rastrear qué clases se están 
     * construyendo actualmente y evitar bucles infinitos (Dependencias Circulares).
     */
    private array $resolving = [];

    /**
     * Registra una dependencia que se reconstruirá en cada llamada a make().
     * Útil para objetos que mantienen un estado temporal y no deben compartirse.
     */
    public function bind(string $abstract, string|object $concrete): void 
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Registra una dependencia compartida (Patrón Singleton).
     * Ideal para conexiones a base de datos, configuraciones o loggers.
     */
    public function singleton(string $abstract, string|Closure $concrete): void 
    {
        $this->bindings[$abstract] = $concrete;
        // Reservamos el slot. Esto le indica a make() que debe guardar 
        // el resultado aquí una vez que lo construya.
        $this->instances[$abstract] = null;
    }

    /**
     * Registra una instancia ya construida como singleton.
     * make() siempre retornará este mismo objeto.
     *
     * Útil para registrar objetos creados en el bootstrap
     * (Request, Response, ErrorHandler) en el contenedor.
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
     * @throws Exception Si la clase no es instanciable o hay dependencias circulares,
     *                   o un parámetro primitivo no puede resolverse.
     */
    public function make(string $abstract): object 
    {        
        // Singleton ya resuelto — retorna la instancia cacheada.
        if(isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Prevención de colapso por dependencia circular (A requiere B, B requiere A)
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

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
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