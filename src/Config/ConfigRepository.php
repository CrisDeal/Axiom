<?php
declare(strict_types=1);

namespace Axiom\Config;

/**
 * ConfigRepository
 *
 * Repositorio central de configuración de Axiom.
 * Carga archivos PHP de una carpeta y los expone mediante
 * dot notation — el nombre del archivo es la clave raíz.
 *
 * Ejemplo:
 *   config/database.php → accesible como 'database.default.host'
 *   config/app.php      → accesible como 'app.name'
 *
 * Uso:
 *   $config->get('database.default.host');         // 'localhost'
 *   $config->get('app.debug', false);              // false si no existe
 *   $config->has('database.default');              // true o false
 *   $config->set('app.name', 'My App');            // asigna en runtime
 */
class ConfigRepository 
{
    /**
     * Configuración cargada indexada por archivo y clave.
     *
     * @var array<string, mixed>
     */
    private array $items = [];

    /**
     * Asigna un valor por dot notation en runtime.
     * Si la clave ya existe por haberla cargado con load(), la sobreescribe.
     *
     * Ejemplo:
     *   $config->set('app.name', 'Axiom App');
     *   $config->set('database.default.host', 'localhost');
     *
     * @param string $key   Clave en dot notation.
     * @param mixed  $value Valor a asignar.
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->items;

        foreach ($keys as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;
    }

    /**
     * Obtiene un valor por dot notation.
     * Retorna $default si la clave no existe.
     *
     * Ejemplo:
     *   $config->get('database.default.host');       // 'localhost'
     *   $config->get('app.missing', 'fallback');     // 'fallback'
     *
     * @param  string $key     Clave en dot notation.
     * @param  mixed  $default Valor por defecto si la clave no existe.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $config = $this->items;

        foreach($keys as $segment) {
            if(!array_key_exists($segment, $config)) {
                return $default;
            }

            $config = $config[$segment];
        }

        return $config;
    }

    /**
     * Verifica si una clave existe en la configuración.
     *
     * Ejemplo:
     *   $config->has('database.default');  // true
     *   $config->has('database.missing');  // false
     *
     * @param  string $key Clave en dot notation.
     * @return bool
     */
    public function has(string $key): bool 
    {
        return $this->get($key, '__AXIOM_MISSING__') !== '__AXIOM_MISSING__';
    }

    /**
     * Retorna toda la configuración cargada.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Carga todos los archivos PHP de una carpeta como configuración.
     * El nombre del archivo (sin extensión) se usa como clave raíz.
     *
     * Ejemplo:
     *   config/database.php → $items['database'] = [...]
     *   config/app.php      → $items['app'] = [...]
     *
     * @param  string $path Ruta absoluta al directorio de configuración.
     * @throws \RuntimeException Si un archivo no retorna un array.
     */
    public function load(string $path): void
    {
        $files = glob($path . '/*.php');

        foreach($files as $file) {
            $config = require $file;
            
            if(!is_array($config)) {
                throw new \RuntimeException("El archivo de configuración '$file' debe retornar un array.");
            }
            
            $name = basename($file, '.php');
            $this->items[$name] = $config;
        }
    }
}