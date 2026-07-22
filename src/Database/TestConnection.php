<?php
namespace Axiom\Database;

use Axiom\Contracts\Database\ConnectionInterface;
use Axiom\Contracts\Database\StatementInterface;

/**
 * Fake connection implementation for testing purposes.
 *
 * This class allows the ORM to be tested without connecting
 * to a real database.
 *
 * It returns fake statement objects that simulate
 * database behavior in-memory.
 */
class TestConnection implements ConnectionInterface {
    /**
     * Prepares a fake SQL statement.
     *
     * @param string $sql
     *
     * @return StatementInterface
     */
    public function prepare(string $sql): StatementInterface {
        return new TestStmt($sql);
    }
}