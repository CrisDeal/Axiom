<?php
namespace Axiom\Contracts\Database;

/**
 * Defines a contract for database connections.
 * 
 * This interface abstracts the underlying database driver
 * (mysqli, PDO, etc.) and allows the ORM to remain decoupled
 * from any specific implementation.
 */
interface ConnectionInterface { 
    /**
     * Prepares an SQL statement for execution.
     * 
     * @param string $sql The SQL query to prepare.
     * @return StatementInterface
     */
    public function prepare(string $sql): StatementInterface;

    /**
     * Returns the last auto-generated ID from the database.
     * Used after INSERT operations on tables with AUTO_INCREMENT primary keys.
     *
     * @return int
     */
    public function lastInsertId(): int;


    /**
     * Begins a database transaction.
     * All subsequent queries run inside this transaction until
     * commit() or rollback() is called.
     *
     * @throws \RuntimeException If the transaction cannot be started.
     */
    public function beginTransaction(): void;

    /**
     * Commits the active transaction.
     * Persists all changes made since beginTransaction().
     *
     * @throws \RuntimeException If there is no active transaction.
     */
    public function commit(): void;

    /**
     * Rolls back the active transaction.
     * Reverts all changes made since beginTransaction().
     *
     * @throws \RuntimeException If there is no active transaction.
     */
    public function rollback(): void;
}