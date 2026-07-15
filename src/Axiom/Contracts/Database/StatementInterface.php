<?php
namespace Axiom\Contracts\Database;


/**
 * Represents a prepared database statement.
 * 
 * This interface abstracts parameter binding, execution
 * and result interval
 */
interface StatementInterface {
    /**
     * Binds parameters to the statement.
     * 
     * @param array<int|string, mixed> $params.
     */
    public function bind(array $params) : void;


    /**
     * Excecutes the prepared statement.
     * 
     * @return bool
     */
    public function execute() : bool;


    /**
     * Returns all rows from the executed statement as an array.
     * 
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll() : array;


    /**
     * Returns the first row or null.
     * 
     * @return array<string, mixed>|null
     */
    public function fetchOne() : ?array;

    /**
     * Returns number of affected rows.
     * 
     * @return int
     */
    public function affectedRows() : int;
}