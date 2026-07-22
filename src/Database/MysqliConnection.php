<?php
namespace Axiom\Database;

use mysqli; 
use RuntimeException;
use Axiom\Contracts\Database\ConnectionInterface;
use Axiom\Contracts\Database\StatementInterface;

/**
 * mysqli implementation of ConnectionInterface.
 * 
 * This class acts as an adapter between the ORM and the mysqli database driver.
 * 
 * Its responsibility is to:
 *  - Wrap a native mysqli connection.
 *  - Prepare SQL statements.
 *  - Return driver-agnostic statements objects.
 * 
 * IMPORTANT
 * This class contains NO business logic and NO ORM logic.
 * It is purely an Axiom component.
*/
class MysqliConnection implements ConnectionInterface {


    /**
     * Native mysqli connection instance.
     * 
     * This object represents an active connection to
     * a MySQL database using the mysqli driver.
     * 
     * @var mysqli
     */
    private mysqli $connection;


    /**
     * Creates a new MySQLi connection wrapper.
     * 
     * @param mysqli $connection An initialized and connected mysqli instance.
     */
    public function __construct(mysqli $connection) {
    if ($connection->connect_errno) {
        throw new \RuntimeException(
            'Error connecting to database: ' . $connection->connect_error
        );
    }    
    $this->connection = $connection;
    }


    /**
     * Prepares an SQL statement for execution.
     * 
     * This method delegates the preparation of the SQL query
     * to the underlying mysqli driver and wraps the resulting
     * mysqli_stmt inside a StatementInterface implementation.
     * 
     * @param string $sql The SQL query to prepare.
     * @return StatementInterface A prepared, driver-agnostic statement.
     * @throws RuntimeException If the SQL statement cannot be prepared.
     */
    public function prepare(string $sql) : StatementInterface {
        $stmt = $this->connection->prepare($sql);

        if($stmt === false) {
            throw new RuntimeException(
                'Error preparing SQL (Mysqli): ' . $this->connection->error
            );
        }

        return new MysqliStmt($stmt);
    }


    /**
     * Returns the last auto-generated ID from the database.
     *
     * Used after INSERT operations on tables with AUTO_INCREMENT
     * primary keys.
     *
     * @return int
     */
    public function lastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    /**
     * Begins a database transaction.
     *
     * @throws \RuntimeException If the transaction cannot be started.
     */
    public function beginTransaction(): void {
        if (!$this->connection->begin_transaction()) {
            throw new \RuntimeException(
                'Error starting transaction: ' . $this->connection->error
            );
        }
    }

    /**
     * Commits the active transaction.
     *
     * @throws \RuntimeException If the commit fails.
     */
    public function commit(): void {
        if (!$this->connection->commit()) {
            throw new \RuntimeException(
                'Error committing transaction: ' . $this->connection->error
            );
        }
    }

    /**
     * Rolls back the active transaction.
     *
     * @throws \RuntimeException If the rollback fails.
     */
    public function rollback(): void {
        if (!$this->connection->rollback()) {
            throw new \RuntimeException(
                'Error rolling back transaction: ' . $this->connection->error
            );
        }
    }
}