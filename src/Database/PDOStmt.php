<?php

namespace Axiom\Database;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Axiom\Contracts\Database\StatementInterface;


/**
 * PDO implementation of StatementInterface.
 *
 * Adapts a PDOStatement to the ORM statement contract.
 */
class PDOStmt implements StatementInterface {
    /**
     * Native PDO statement instance.
     *
     * @var PDOStatement
     */
    private PDOStatement $stmt;

    /**
     * Creates a new PDO statement wrapper.
     * 
     * @param PDOStatement $stmt
     */
    public function __construct(PDOStatement $stmt) {
        $this->stmt = $stmt;
    }

    /**
     * Binds parameters to the statement with strict typing.
     *
     * @param array<int|string, mixed> $params
     */
    public function bind(array $params) : void {
        foreach ($params as $key => $value) {
            // Inferencia de tipos nativos de PDO
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };

            $this->stmt->bindValue(
                is_int($key) ? $key + 1 : $key,
                $value,
                $type
            );
        }
    }

    /**
     * Executes the statement.
     *
     * @return bool
     * @throws RuntimeException If execution fails at the driver level.
     */
    public function execute() : bool {
        try {
            $this->stmt->execute();
            return true;
        } catch(PDOException $e) {
            throw new RuntimeException(
                'Error executing PDO statement: ' . $e->getMessage()
            );
        }
    }

    /**
     * Fetches all result rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll() : array {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches the first row or null.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne() : ?array {
        $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Returns affected rows.
     *
     * @return int
     */
    public function affectedRows() : int {
        return $this->stmt->rowCount();
    }
}