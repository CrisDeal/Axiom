<?php
declare(strict_types=1);

namespace Axiom\Database;

use Axiom\Contracts\Database\ConnectionInterface;
use RuntimeException;

/**
 * Gestor central de conexiones a bases de datos de Axiom.
 * 
 * Utiliza carga perezosa (Lazy Loading) para instanciar las conexiones
 * únicamente cuando son solicitadas por primera vez.
 */
class ConnectionManager
{
    private static array $connections = [];

    /**
     * Registra una nueva conexión sin instanciarla aún.
     * 
     * @param string $name Nombre de la conexión (ej. 'default', 'replica').
     * @param callable $resolver Función que debe retornar un ConnectionInterface.
     */
    public static function register(string $name, callable $resolver): void
    {
        self::$connections[$name] = [
            'resolver' => $resolver,
            'instance' => null
        ];
    }

    /**
     * Obtiene una instancia de conexión por su nombre.
     * Si es la primera vez que se solicita, la instanciará.
     * 
     * @param string $name
     * @return ConnectionInterface
     * @throws RuntimeException Si la conexión no existe o el resolver es inválido.
     */
    public static function get(string $name): ConnectionInterface 
    {
        if(!isset(self::$connections[$name])) {
            throw new RuntimeException("La conexión de base de datos '{$name}', no ha sido registrada aun en 'config/database.php.'");
        }

        if(self::$connections[$name]['instance'] === null) {
            $instance = (self::$connections[$name]['resolver'])();

            if (!$instance instanceof ConnectionInterface) {
                throw new RuntimeException("El resolver para la conexión '{$name}' debe retornar una instancia válida de Axiom\Contracts\Database\ConnectionInterface.");
            }

            self::$connections[$name]['instance'] = $instance;
        }

        return self::$connections[$name]['instance'];
    }
}