<?php
declare(strict_types=1);

namespace Axiom\Providers;

use mysqli;
use mysqli_sql_exception;
use PDO;
use PDOException;
use RuntimeException;
use Axiom\DI\Container;
use Axiom\Config\ConfigRepository;
use Axiom\Database\ConnectionManager;
use Axiom\Database\MysqliConnection;
use Axiom\Database\PDOConnection;
use Axiom\Contracts\Database\ConnectionInterface;
use Axiom\Contracts\Providers\ServiceProviderInterface;

// AGREGAR CONNECTION FACTORY PARA CADA DRIVER DE DB SUPORTADO

/**
 * DatabaseServiceProvider
 *
 * Registra y configura la capa de base de datos de Axiom.
 * Lee la configuración desde config/database.php del proyecto consumidor
 * y registra las conexiones en el ConnectionManager de forma lazy.
 *
 * Estructura esperada en config/database.php:
 *
 *   return [
 *       'default' => 'axiom',
 *       'connections' => [
 *           'axiom' => [
 *               'driver'   => 'mysqli',
 *               'host'     => 'localhost',
 *               'user'     => 'root',
 *               'password' => 'secret',
 *               'database' => 'myapp',
 *               'port'     => 3306,
 *               'charset'  => 'utf8mb4',
 *           ],
 *           'analytics' => [ ... ],
 *       ],
 *   ];
 *
 * La clave 'default' define qué conexión usarán los modelos
 * que no especifiquen un $db explícitamente.
 */
class DatabaseServiceProvider implements ServiceProviderInterface
{
    /**
     * Registra todas las conexiones en el ConnectionManager de forma lazy.
     * La conexión 'default' se registra también bajo el nombre 'default'
     * para que los modelos que no especifiquen $db la encuentren automáticamente.
     */
    public function register(Container $container): void
    {
        $config      = $container->make(ConfigRepository::class);
        $connections = $config->get('database.connections', []);
        $default     = $config->get('database.default', null);

        // No hay conexiones configuradas — la DB es opcional.
        if (empty($connections)) {
            return; 
        }

        // Valida que la conexión default exista en connections.
        if ($default !== null && !array_key_exists($default, $connections)) {
            throw new RuntimeException(
                "DatabaseServiceProvider: La conexión default '$default' " .
                "no está definida en database.connections."
            );
        }

        foreach($connections as $name => $connectionConfig) {
            $this->validateConfig($name, $connectionConfig);

            // Refactorizar puerto de string a numero
            if(isset($connectionConfig['port']) && is_string($connectionConfig['port'])) {
                $connectionConfig['port'] = (int)$connectionConfig['port'];
            }

            // Registra la conexión bajo su nombre real.
            ConnectionManager::register(
                $name, 
                fn() => $this->createConnection($connectionConfig)
            );

            // Si es la default, creamos un alias 'default' en ConnectionManager y segurar que devuelva la misma instancia en lugar de crear una nueva.
            if ($default === $name) {
                ConnectionManager::register(
                    'default',
                    fn() => ConnectionManager::get($name)
                );
            }
        }
    }

    public function boot(Container $container): void
    {
        // Sin lógica de arranque necesaria
    }

    /**
     * Resuelve la implementación correcta según el driver configurado.
     *
     * @throws RuntimeException Si el driver no está soportado.
     */
    private function createConnection(array $config): ConnectionInterface
    {
        return match($config['driver']) {
            'mysqli' => $this->createMysqliConnection($config),
            'pdo' => $this->createPDOConnection($config),
            default => throw new RuntimeException("DatabaseServiceProvider: Driver {$config['driver']} no soportado en la configuracion: " .
            "Drivers disponibles: [mysqli, pdo]."),
        };
    }

    /**
     * Crea y verifica una conexión PDO.
     */
    private function createPDOConnection(array $config): PDOConnection
    {
        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $db   = $config['database'];
        $charset = $config['charset'] ?? 'utf8mb4';

        // Construcción del DSN (Data Source Name) para MySQL
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $config['user'], $config['password'], $options);
            return new PDOConnection($pdo);
        } catch (PDOException $e) {
            throw new RuntimeException(
                "DatabaseServiceProvider (PDO): Error al conectar a la base de datos '{$db}'. " . $e->getMessage()
            );
        }
    }

    /**
     * Crea y verifica una conexión MySQLi.
     *
     * @throws RuntimeException Si la conexión falla.
     */
    private function createMysqliConnection(array $config): MysqliConnection
    {
        try {
            // Activar reporte estricto de mysqli temporalmente para capturar el error
            mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

            $mysqli = new mysqli(
                $config['host'],
                $config['user'],
                $config['password'],
                $config['database'],
                $config['port'] ?? 3306
            );

            if($mysqli->connect_errno) {
                throw new RuntimeException("DatabaseServiceProvider: Error al conectar a la base de datos '{$config['database']}'" . $mysqli->connect_error);
            }
    
            $mysqli->set_charset($config['charset'] ?? 'utf8mb4');
    
            return new MysqliConnection($mysqli);
        } catch (mysqli_sql_exception $e) {
            throw new RuntimeException("DatabaseServiceProvider (MySQLi): Error al conectar a la base de datos '{$config['database']}'. " . $e->getMessage());
        }

    }

    /**
     * Valida que la vonfiguracion de una conexion tenga los campos reuqeridos.
     * 
     * @throws RuntimeException Si falta algun campo requerido.
     */
    private function validateConfig(string $name, array $config): void 
    {
        $required = ['driver', 'host', 'user', 'password', 'database'];

        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new RuntimeException(
                    "DatabaseServiceProvider: Falta el campo '$field' " .
                    "en la conexión '$name' de config/database.php."
                );
            }
        }
    }
}