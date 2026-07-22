<?php
namespace Axiom\Database;

use RuntimeException;
use InvalidArgumentException;
use Axiom\Contracts\Database\ConnectionInterface;
use Axiom\Contracts\Database\StatementInterface;

/**
 * ActiveRecord
 *
 * Base Active Record implementation for Axiom.
 * Consumes the Database Abstraction Layer (DBAL) to provide
 * object-oriented CRUD operations without tying the models
 * to a specific database driver.
 *
 * Features:
 *   - Dynamic attributes via __get/__set
 *   - Mass-assignment protection via static $columns
 *   - Automatic create/update detection via save()
 *   - Driver-agnostic through ConnectionInterface
 *   - Transaction support via transaction()
 *
 * Usage:
 *   class User extends ActiveRecord {
 *       protected static string $table   = 'users';
 *       protected static array  $columns = ['name', 'email', 'password'];
 *   }
 *
 *   $user = new User(['name' => 'Ana', 'email' => 'ana@example.com']);
 *   $user->save();
 *
 *   $users = User::where(['role' => 'admin']);
 *   $user  = User::find(1);
 */
class ActiveRecord implements \JsonSerializable {
    /**
     * Valid SQL operators allowed in filter conditions.
     * Protects against SQL injection in dynamic query building.
     *
     * @var string[]
     */
    private static array $allowedOperators = [
        '=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'
    ];

    /** @var array<string, mixed> The model's dynamic attributes mapping. */
    protected array $attributes = [];


    // =========================================================
    // MODEL CONFIGURATION
    // =========================================================

    /** @var string The database name associated with the model. Initialized as "default". */
    protected static string $db = 'default';
    
    /** @var string The database table associated with the model. */
    protected static string $table = '';
    
    /** @var string The primary key for the model. */
    protected static string $primaryKey = 'id';

    /**
     * Mass-assignable columns whitelist.
     * Only columns listed here can be set via constructor or __set.
     *
     * @var string[]
     */
    protected static array $columns = [];


    // =========================================================
    // MAGIC METHODS
    // =========================================================

    /**
     * Initializes the model and hydrates attributes via __set validation.
     *
     * @throws RuntimeException         If $columns is not defined.
     * @throws InvalidArgumentException If an attribute is not in $columns.
     */
    public function __construct(array $attributes = []) 
    {
        $this->assertColumnsDefined();

        foreach ($attributes as $key => $value) {
            $this->$key = $value; // This triggers __set() for validation
        }
    }

    /**
     * Intercepts property assignment and validates against $columns whitelist.
     * The primary key is always allowed regardless of $columns.
     *
     * @throws InvalidArgumentException If the property is not in $columns.
     */
    public function __set(string $name, mixed $value): void 
    {
        if (!empty(static::$columns)) {
            if (!in_array($name, static::$columns) && $name !== static::$primaryKey) {
                throw new InvalidArgumentException(
                    "ActiveRecord: The property '{$name}' is not a valid column for model " . static::class
                );
            }
        }

        $this->attributes[$name] = $value;
    }

    /**
     * Intercepts property reading for dynamic attributes.
     * @return null if the attribute is not set.
     */
    public function __get(string $name): mixed 
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Allows the use of isset() or empty() on dynamic attributes.
     */
    public function __isset(string $name): bool 
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Serializes the model's attributes for json_encode().
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }    


    // =========================================================
    // DATABASE ACCESS
    // =========================================================

    /**
     * Resolves the active database connection from ConnectionManager.
     *
     * @throws RuntimeException If the connection is not registered.
     */
    public static function getDB(string $name): ConnectionInterface 
    {
        $conn = ConnectionManager::get($name);

        if (!$conn) {
            throw new RuntimeException(
                "ActiveRecord: Connection '$name' not found. " .
                "Register it in config/database.php."
            );
        }

        return $conn;
    }


    // =========================================================
    // CRUD OPERATIONS
    // =========================================================

    /**
     * Persists the model to the database.
     * Automatically determines whether to INSERT or UPDATE
     * based on the presence of the primary key.
     */
    public function save(): bool 
    {
        $pk = static::$primaryKey;
        return (isset($this->$pk) && !is_null($this->$pk)) 
            ? $this->update() 
            : $this->create();
    }

    /**
     * Inserts the model as a new row in the database.
     * Sets the primary key on the instance after a successful insert.
     */
    public function create(): bool {
        $dbAttributes = $this->getAttributesForDb();
        $table        = static::$table;
        $pk           = static::$primaryKey;
        $cols         = implode(', ', array_keys($dbAttributes));
        $placeholders = implode(', ', array_fill(0, count($dbAttributes), '?'));
        
        $query = "INSERT INTO $table ($cols) VALUES ($placeholders)";
        $params = array_values($dbAttributes);

        // prepareQuery ejecuta el statement — si no lanza excepción, fue exitoso.
        static::prepareQuery($query, $params);

        // Asigna el id generado por la DB a la instancia.
        $this->attributes[$pk] = static::getDB(static::$db)->lastInsertId();

        return true;
    }

    /**
     * Updates the existing row in the database matching the primary key.
     * Retorna true si la query se ejecutó sin errores,
     * independientemente de si los valores cambiaron.
     */
    public function update(): bool {
        $dbAttributes = $this->getAttributesForDb();
        $set          = implode(', ', array_map(fn($col) => "$col = ?", array_keys($dbAttributes)));
        $table        = static::$table;
        $pk           = static::$primaryKey;

        $query    = "UPDATE $table SET $set WHERE $pk = ?";
        $params   = array_values($dbAttributes);
        $params[] = $this->$pk;
        
        // Si no lanza excepción, el update fue exitoso.
        static::prepareQuery($query, $params);
        return true;
    }

    /**
     * Deletes the model's row from the database.
     */
    public function delete(): bool {
        $table = static::$table;
        $pk    = static::$primaryKey;

        $query = "DELETE FROM $table WHERE $pk = ?";
        $stmt  = static::prepareQuery($query, [$this->$pk]);

        return $stmt->affectedRows() > 0;
    }

    /**
     * Retrieves multiple rows matching optional filters.
     *
     * Simple filter:   User::where(['role' => 'admin'])
     * Operator filter: User::where(['age' => ['>=', 18]])
     *
     * @param  array<string, mixed> $filters
     * @return static[]
     */
    public static function where(array $filters = []): array 
    {
        // $table = static::$table;
        // $query = "SELECT * FROM $table";
        // $params = [];

        // if(!empty($filters)) {
        //     $conditions = [];
        //     foreach($filters as $col => $value) {
        //         if(is_array($value)) {
        //             $conditions[] = "$col {$value[0]} ?";
        //             $params[] = $value[1];
        //         } else {
        //             $conditions[] = "$col = ?";
        //             $params[] = $value;
        //         }
        //     }
        //     $query .= " WHERE " . implode(' AND ', $conditions);
        // }
        // return static::executeRead($query, $params);
        [$query, $params] = static::buildSelectQuery($filters);
        return static::executeRead($query, $params);
    }

    /**
     * Retrieves the first row matching optional filters.
     * Returns null if no row matches.
     *
     * @param  array<string, mixed> $filters
     * @return static|null
     */
    public static function first(array $filters = []): ?static 
    {
        // $table = static::$table;
        // $query = "SELECT * FROM $table";
        // $params = [];

        // if(!empty($filters)) {
        //     $conditions = [];
        //     foreach($filters as $col => $value) {
        //         if(is_array($value)) {
        //             $conditions[] = "$col {$value[0]} ?";
        //             $params[] = $value[1];
        //         } else {
        //             $conditions[] = "$col = ?";
        //             $params[] = $value;
        //         }
        //     }
        //     $query .= " WHERE " . implode(' AND ', $conditions);
        // }
        
        // $query .= " LIMIT 1"; 
        // $results = static::executeRead($query, $params);
        
        // return $results[0] ?? null;
        [$query, $params] = static::buildSelectQuery($filters);
        $query   .= ' LIMIT 1';
        $results  = static::executeRead($query, $params);
        return $results[0] ?? null;
    }

    /**
     * Retrieves all rows from the table.
     *
     * @return static[]
     */
    public static function all(): array 
    {
        $query = 'SELECT * FROM ' . static::$table;
        return static::executeRead($query);
    }

    /**
     * Retrieves a single row by primary key.
     * Returns null if not found.
     *
     * @param  int|string $id Primary key value.
     * @return static|null 
     */
    public static function find(int|string $id): ?static 
    {
        $table   = static::$table;
        $pk      = static::$primaryKey;
        $query   = "SELECT * FROM $table WHERE $pk = ? LIMIT 1";
        $results = static::executeRead($query, [$id]);
        return $results[0] ?? null;
    }

    /**
     * Executes a callable inside a database transaction.
     * Automatically commits on success or rolls back on failure.
     *
     * Usage:
     *   User::transaction(function() use ($user, $profile) {
     *       $user->save();
     *       $profile->save();
     *   });
     *
     * @throws \Throwable Re-throws any exception after rolling back.
     */
    public static function transaction(callable $fn): void 
    {
        $conn = static::getDB(static::$db);
        $conn->beginTransaction();

        try {
            $fn();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /**
     * Executes a raw SQL query.
     * SELECT queries return an array of hydrated model instances.
     * Write queries return true if at least one row was affected.
     *
     * @param  string            $query
     * @param  array<int, mixed> $params
     * @return static[]|bool
     */
    public static function rawQuery(string $query, array $params = []): array|bool 
    {
        return stripos($query, 'select') !== false
            ? static::executeRead($query, $params)
            : static::executeWrite($query, $params);
    }


    // =========================================================
    // INTERNAL HELPERS
    // =========================================================

    /**
     * Builds a SELECT query with optional WHERE conditions.
     * Validates operators to prevent SQL injection.
     *
     * @param  array<string, mixed> $filters
     * @return array{0: string, 1: array}  [query, params]
     *
     * @throws InvalidArgumentException If an invalid operator is used.
     */
    private static function buildSelectQuery(array $filters): array 
    {
        $query  = 'SELECT * FROM ' . static::$table;
        $params = [];

        if (!empty($filters)) {
            $conditions = [];

            foreach ($filters as $col => $value) {
                if (is_array($value)) {
                    $operator = strtoupper($value[0]);

                    if (!in_array($operator, self::$allowedOperators, true)) {
                        throw new InvalidArgumentException(
                            "ActiveRecord: Invalid operator '$operator'. " .
                            "Allowed: " . implode(', ', self::$allowedOperators)
                        );
                    }

                    $conditions[] = "$col $operator ?";
                    $params[]     = $value[1];
                } else {
                    $conditions[] = "$col = ?";
                    $params[]     = $value;
                }
            }

            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        return [$query, $params];
    }

    /**
     * Prepares, binds and executes a query via the DBAL.
     *
     * @throws RuntimeException If the connection is not found.
     */
    private static function prepareQuery(string $query, array $params): StatementInterface 
    {
        $conn = self::getDB(static::$db);
        if (!$conn) {
            throw new RuntimeException("ActiveRecord: Database connection '" . static::$db . "' not found.");
        }

        $stmt = $conn->prepare($query);
        
        if(!empty($params)) {
            $stmt->bind($params);
        }

        $stmt->execute();

        return $stmt;
    }

    /**
     * Executes a SELECT query and returns hydrated model instances.
     *
     * @return static[]
     */
    protected static function executeRead(string $query, array $params = []): array {
        $stmt = static::prepareQuery($query, $params);
        $rows = $stmt->fetchAll();

        return array_map(
            fn($row) => static::createObject($row),
            $rows
        );
    }

    /**
     * Executes INSERT, UPDATE, DELETE queries.
     */
    protected static function executeWrite(string $query, array $params = []): bool 
    {
        $stmt = static::prepareQuery($query, $params);
        return $stmt->affectedRows() > 0; 
    }

    /**
     * Hydrates a model instance directly from a database row.
     * Bypasses __set validation since data comes from the database.
     *
     * @param  array<string, mixed> $row
     * @return static
     */
    public static function createObject(array $row): static {
        $obj             = new static();
        $obj->attributes = $row;
        return $obj;
    }

    /**
     * Returns the model's attributes ready for INSERT/UPDATE,
     * excluding the primary key.
     *
     * @return array<string, mixed>
     */
    protected function getAttributesForDb(): array {
        $attrs = $this->attributes;
        unset($attrs[static::$primaryKey]);
        return $attrs;
    }


    /**
     * Ensures the model explicitly defines its $columns whitelist.
     *
     * @throws RuntimeException If $columns is empty.
     */
    private function assertColumnsDefined(): void
    {
        if (empty(static::$columns)) {
            throw new RuntimeException(
                'ActiveRecord: Model ' . static::class .
                ' must define a non-empty static $columns property.'
            );
        }
    }




 




}