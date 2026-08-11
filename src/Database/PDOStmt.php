<?php

declare(strict_types=1);

namespace Axiom\Database;

use Axiom\Contracts\Database\StatementInterface;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Implementación de StatementInterface utilizando PDO.
 *
 * Envuelve un PDOStatement nativo para cumplir con el contrato del ORM,
 * manejando el tipado dinámico y la conversión de excepciones.
 */
class PDOStmt implements StatementInterface {
    
    /**
     * Crea un nuevo wrapper para el statement de PDO.
     * 
     * @param PDOStatement $stmt Instancia nativa del statement preparado.
     */
    public function __construct(
        private PDOStatement $stmt
    ) {}

    /**
     * Vincula parámetros a la consulta con tipado estricto inferido.
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

            // PDO usa índices basados en 1 para parámetros anónimos (?), 
            // pero los arrays de PHP usan índices basados en 0.
            $this->stmt->bindValue(
                is_int($key) ? $key + 1 : $key,
                $value,
                $type
            );
        }
    }

    /**
     * Ejecuta la consulta preparada.
     *
     * @return bool
     * @throws RuntimeException Si la ejecución falla a nivel del driver.
     */
    public function execute() : bool {
        try {
            $this->stmt->execute();
            return true;
        } catch(PDOException $e) {
            throw new RuntimeException(
                'Error ejecutando el statement de PDO: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Obtiene todas las filas del resultado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll() : array {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Iterador eficiente para conjuntos de resultados masivos (Generador).
     * 
     * Mantiene en memoria solo un registro a la vez.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function fetch() : \Generator {
        // Obtenemos una fila a la vez hasta que PDO devuelva false
        while ($row = $this->stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Obtiene la primera fila del resultado, o null si no hay resultados.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne() : ?array {
        $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Devuelve el número de filas afectadas por la última consulta.
     *
     * @return int
     */
    public function affectedRows() : int {
        return $this->stmt->rowCount();
    }
}