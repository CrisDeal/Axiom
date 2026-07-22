<?php

namespace Axiom\Database;

use PDO;
use PDOException;
use RuntimeException;
use Axiom\Contracts\Database\ConnectionInterface;
use Axiom\Contracts\Database\StatementInterface;


/**
 * PDO implementation of ConnectionInterface.
 *
 * This class adapts a native PDO connection to the
 * ORM's database abstraction layer.
 *
 * It allows the ORM to work with PDO without
 * knowing anything about PDO-specific APIs.
 */
class PDOConnection implements ConnectionInterface
{
    /**
     * Native PDO instance.
     *
     * @var PDO
     */
    private PDO $connection;


    /**
     * Creates a new PDO connection wrapper.
     *
     * @param PDO $connection An already configured PDO instance.
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;

        // Ensure PDO throws exceptions for errors
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

     
    /**
     * Prepares an SQL statement using PDO.
     *
     * @param string $sql The SQL query to prepare.
     * @return StatementInterface
     * @throws RuntimeException If the statement cannot be prepared.
     */
    public function prepare(string $sql): StatementInterface
    {
        try {
            $stmt = $this->connection->prepare($sql);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Error preparing SQL (PDO): ' . $e->getMessage()
            );
        }

        return new PDOStmt($stmt);
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

}