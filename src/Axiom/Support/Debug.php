<?php
declare(strict_types=1);

namespace Axiom\Support;

/**
 * Debug
 *
 * Utilidades de depuración para desarrollo.
 * Todos los métodos de esta clase están pensados exclusivamente
 * para uso durante el desarrollo — nunca deben llegar a producción.
 *
 * Equivalente al helper dd() (dump and die) de Laravel.
 */
class Debug {

    /**
     * Imprime el contenido de una variable sin detener la ejecución.
     * Útil para inspeccionar valores dentro de loops o pipelines.
     *
     * Ejemplo:
     *   foreach ($users as $user) {
     *       Debug::dump($user); // inspecciona cada iteración
     *   }
     *
     * @param mixed $data Variable a inspeccionar.
     */
    public static function dump(mixed $data): void 
    {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
    }

    /**
     * Imprime el contenido de una variable de forma legible y detiene
     * la ejecución de la aplicación.
     *
     * Útil para inspeccionar el estado de cualquier variable en un
     * punto específico del flujo sin que la aplicación continúe.
     *
     * Ejemplo:
     *   Debug::dd($request->body);
     *   Debug::dd($user);
     *
     * @param mixed $data Variable a inspeccionar.
     * @return never      Siempre detiene la ejecución con exit.
     */
    public static function dd(mixed $data): never 
    {
        echo "<pre>";
        var_dump($data);
        echo "</pre>";
        exit;
    }

    /**
     * Imprime la variable junto con el stack trace completo
     * y detiene la ejecución. (Dump, Die with trace)
     *
     * Útil cuando necesitas saber desde dónde exactamente
     * se está llamando un método o desde qué punto del flujo
     * viene un valor inesperado.
     *
     * Ejemplo:
     *   Debug::ddd($response);
     *
     * @param  mixed $data Variable a inspeccionar.
     * @return never       Siempre detiene la ejecución.
     */
    public static function ddd(mixed $data): never 
    {
        echo '<pre>';
        var_dump($data);
        echo PHP_EOL;
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        echo '</pre>';
        exit;
    }

    /**
     * Mide el tiempo de ejecución de un callable en milisegundos.
     * Útil para detectar cuellos de botella sin instalar herramientas externas.
     *
     * Ejemplo:
     *   $ms = Debug::measure(fn() => $db->query('SELECT * FROM users'));
     *   Debug::dd($ms); // ej: 42.3ms
     *
     * @param  callable $fn       Función a medir.
     * @return string             Tiempo transcurrido en milisegundos.
     */
    public static function measure(callable $fn): string 
    {
        $start = hrtime(true);
        $fn();
        $end = hrtime(true);

        $ms = round(($end - $start) / 1_000_000, 2);
        return "{$ms}ms";
    }
}