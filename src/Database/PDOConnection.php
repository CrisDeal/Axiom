<?php

declare(strict_types=1);

namespace Axiom\Database;

use Axiom\Contracts\Database\ConnectionInterface;
use Axiom\Contracts\Database\StatementInterface;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Implementación de ConnectionInterface utilizando PDO.
 * 
 * Esta clase actúa como un envoltorio (wrapper) alrededor de la clase nativa PDO de PHP.
 * Su propósito principal es cumplir con el contrato de Axiom y traducir las 
 * excepciones nativas (PDOException) a excepciones estándar del framework.
 */
class PDOConnection implements ConnectionInterface
{
    /**
     * @param PDO $connection Instancia nativa de PDO ya inicializada.
     * 
     * NOTA PARA DEVS: 
     * La configuración de la conexión (DSN, credenciales, atributos como ERRMODE_EXCEPTION) 
     * DEBE realizarse antes de inyectar la dependencia aquí. Esta clase no debe 
     * mutar el estado del objeto PDO, solo utilizarlo.
     */
    public function __construct(
        private PDO $connection
    ) { }

    /**
     * Prepara una consulta SQL y devuelve un Statement de Axiom.
     *
     * @param string $sql La consulta SQL con marcadores de posición (ej. :email, ?).
     * @return StatementInterface
     * @throws RuntimeException Si hay un error de sintaxis o la base de datos rechaza la consulta.
     */
    public function prepare(string $sql): StatementInterface
    {
        try {
            $stmt = $this->connection->prepare($sql);
        } catch (PDOException $e) {
            // Envolvemos la PDOException para que el ORM no quede acoplado a PDO.
            throw new RuntimeException(
                'Error al preparar la consulta SQL (PDO): ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        // Devolvemos nuestra propia implementación del Statement, no el PDOStatement nativo.
        return new PDOStmt($stmt);
    }

    /**
     * Obtiene el ID generado por la última operación INSERT.
     *
     * @return int|string Devuelve int para AUTO_INCREMENT o string para secuencias/UUIDs.
     */
    public function lastInsertId(): int|string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Inicia una transacción de base de datos.
     * 
     * Esencial para operaciones múltiples que deben ser atómicas (todo o nada).
     *
     * @throws RuntimeException Si la transacción falla al iniciar o si PDO lanza un error.
     */
    public function beginTransaction(): void
    {
        try {
            if (!$this->connection->beginTransaction()) {
                throw new RuntimeException("No se pudo iniciar la transacción en la base de datos.");
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Error al iniciar transacción en la base de datos: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Confirma la transacción actual, guardando los cambios permanentemente.
     *
     * @throws RuntimeException Si el commit falla o si no hay transacción activa.
     */
    public function commit(): void
    {
        try {
            if (!$this->connection->commit()) {
                throw new RuntimeException("No se pudo confirmar (commit) la transacción.");
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Error al hacer commit en la transacción: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Revierte la transacción actual, descartando todos los cambios no confirmados.
     *
     * @throws RuntimeException Si el rollback falla o si no hay transacción activa.
     */
    public function rollback(): void
    {
        try {
            if (!$this->connection->rollBack()) {
                throw new RuntimeException("No se pudo revertir (rollback) la transacción.");
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Error al hacer rollback en la transacción: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}