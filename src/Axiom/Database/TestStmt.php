<?php

namespace Axiom\Database;

use Axiom\Contracts\Database\StatementInterface;


/**
 * Fake statement used for testing.
 *
 * Simulates execution and result fetching
 * without any real database interaction.
 */
class TestStmt implements StatementInterface {
    private string $sql;
    private array $params = [];

    /**
     * @param string $sql
     */
    public function __construct(string $sql) {
        $this->sql = $sql;
    }

    /**
     * Stores bound parameters.
     *
     * @param array<int|string, mixed> $params
     */
    public function bind(array $params): void {
        $this->params = $params;
    }

    /**
     * Simulates query execution.
     *
     * @return bool
     */
    public function execute(): bool {
        // Always succeeds in test mode
        return true;
    }

    /**
     * Returns fake result set.
     *
     * @return array
     */
    public function fetchAll(): array {
        return [];
    }

    /**
     * Returns fake single row.
     *
     * @return array|null
     */
    public function fetchOne(): ?array {
        return null;
    }

    /**
     * Returns fake affected rows count.
     *
     * @return int
     */
    public function affectedRows(): int {
        return 1;
    }
}