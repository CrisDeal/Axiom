<?php
namespace Axiom\Database;

use mysqli_stmt;
use RuntimeException;
use Axiom\Contracts\Database\StatementInterface;

 
/**
 * mysqli implementation of StatementInterface.
 * 
 * This class acts as an adapter, wrapping the native mysqli_stmt object.
 * It provides a clean, object-oriented API for binding parameters,
 * executing queries, and fetching results without exposing the undearlying
 * MySQLi driver specifics to the rest of the application.
 */
class MysqliStmt implements StatementInterface {
    /**
     * The native MySQLi statement instance.
     * 
     * @var mysqli_stmt
     */
    private mysqli_stmt $stmt;


    /**
     * Initializes the statement adapter.
     * 
     * @param mysqli_stmt $stmt A successfully prepared native MySQLi statement.
     */
    public function __construct(mysqli_stmt $stmt) {
        $this->stmt = $stmt;
    }


    /**
     * Binds parameters to the prepared statement dynamically.
     * 
     * Automatically infers the correct MySQLi type binding ('i' for integers,
     * 'd' for doubles/floats, 's' for strings) based on the provided PHP values.
     * 
     * @params array<int|string, mixed> $params The parameters to bind.
     * @return void
     * @throws RuntimeException If the parameter binding process fails.
     */
    public function bind(array $params) : void {
        if(empty($params)) {
            return;
        }

        $types = '';
        foreach($params as $param) {
            $types .= match(true) {
                is_int($param), is_bool($param) => 'i',
                is_float($param) => 'd',
                default => 's',
            };
        }

        if(!$this->stmt->bind_param($types, ...$params)) {
            throw new RuntimeException(
                'Error binding params: ' . $this->stmt->error
            );
        }
    }


    /**
     * Executes the prepared statement.
     * 
     * @return bool True on success.
     * @throws RuntimeException If the execution fails at the database level.
     */
    public function execute() : bool {
        if(!$this->stmt->execute()) {
            throw new RuntimeException(
                'Error executing Mysqli statement: ' . $this->stmt->error
            );
        }

        return true;
    }


    /**
     * Fetches all rows from the executed statement.
     * 
     * If the statement was a write operation (INSERT, UPDATE, DELETE)
     * which does not yield a result set, it safely returns an empty array.
     * 
     * @return array<int, array<string, mixed>> An array of associative
     * arrays representing the rows.
     */
    public function fetchAll() : array {
        $result = $this->stmt->get_result();
        
        if($result === false) {
            return [];
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }


    /**
     * Fetches the first row from the executed statement.
     * 
     * @return array<string, mixed>|null The associative
     * array of the first row, or null if no results.
     */
    public function fetchOne() : ?array {
        $result = $this->fetchAll();
        return $result[0] ?? null;
    }


    /**
     * Retrieves the number of rows affected by the last execution.
     * 
     * Useful for verifying the impact of INSERT, UPDATE, or DELETE operations.
     * 
     * @return int The number of affected rows.
     */
    public function affectedRows() : int {
        return $this->stmt->affected_rows;
    }
} 