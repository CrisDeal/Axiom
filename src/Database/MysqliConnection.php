<?php

declare(strict_types=1);

namespace Axiom\Database;

use mysqli; 
use mysqli_sql_exception;
use RuntimeException;
use Axiom\Contracts\Database\ConnectionInterface;
use Axiom\Contracts\Database\StatementInterface;

/**
 * Implementación de ConnectionInterface utilizando la extensión MySQLi.
 * 
 * Actúa como un adaptador (Adapter) para la clase nativa mysqli de PHP.
 * Está diseñada de forma defensiva para soportar tanto el manejo moderno de 
 * excepciones (PHP 8.1+) como el comportamiento clásico de retornos booleanos.
 */
class MysqliConnection implements ConnectionInterface {
    
    /**
     * @param mysqli $connection Instancia nativa de MySQLi ya conectada.
     * 
     * NOTA PARA DEVS: 
     * La conexión real a la base de datos (host, usuario, contraseña) DEBE 
     * realizarse antes de inyectar la dependencia aquí. 
     */
    public function __construct(
        private mysqli $connection
    ) {
        // Mantenemos la validación manual (connect_errno) por si el framework 
        // se ejecuta en un entorno donde el reporte estricto de MySQLi 
        // (MYSQLI_REPORT_STRICT) fue desactivado intencionalmente.
        if ($this->connection->connect_errno) {
            throw new RuntimeException(
                'Error al conectar a la base de datos (Mysqli): ' . $this->connection->connect_error
            );
        }    
    }

    /**
     * Prepara una consulta SQL y devuelve un Statement de Axiom.
     *
     * @param string $sql La consulta SQL con marcadores de posición (?).
     * @return StatementInterface Instancia de MysqliStmt.
     * @throws RuntimeException Si hay un error de sintaxis o la consulta es rechazada.
     */
    public function prepare(string $sql) : StatementInterface {
        try {
            $stmt = $this->connection->prepare($sql);
            
            // Fallback defensivo: Si el modo de reporte no lanza excepciones,
            // prepare() devolverá false en caso de error.
            if ($stmt === false) {
                throw new RuntimeException(
                    'Error al preparar la consulta SQL (Mysqli): ' . $this->connection->error
                );
            }
            
            // Retornamos el wrapper del statement, no el objeto mysqli_stmt nativo.
            return new MysqliStmt($stmt);
            
        } catch (mysqli_sql_exception $e) {
            // Unificamos las excepciones nativas bajo el estándar de Axiom.
            throw new RuntimeException(
                'Excepción al preparar la consulta SQL (Mysqli): ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Obtiene el ID generado por la última operación INSERT.
     *
     * @return int|string
     */
    public function lastInsertId(): int|string
    {
        return $this->connection->insert_id;
    }

    /**
     * Inicia una transacción de base de datos.
     *
     * @throws RuntimeException Si el motor de base de datos rechaza iniciar la transacción.
     */
    public function beginTransaction(): void {
        try {
            if (!$this->connection->begin_transaction()) {
                throw new RuntimeException('No se pudo iniciar la transacción en la base de datos: ' . $this->connection->error);
            }
        } catch (mysqli_sql_exception $e) {
            throw new RuntimeException('Error al iniciar transacción en la base de datos: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Confirma la transacción actual, guardando los cambios permanentemente.
     *
     * @throws RuntimeException Si el commit falla a nivel del driver.
     */
    public function commit(): void {
        try {
            if (!$this->connection->commit()) {
                throw new RuntimeException('No se pudo confirmar (commit) la transacción: ' . $this->connection->error);
            }
        } catch (mysqli_sql_exception $e) {
            throw new RuntimeException('Error al hacer commit en la transacción: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Revierte la transacción actual, descartando los cambios realizados.
     *
     * @throws RuntimeException Si el rollback falla a nivel del driver.
     */
    public function rollback(): void {
        try {
            if (!$this->connection->rollback()) {
                throw new RuntimeException('No se pudo revertir (rollback) la transacción: ' . $this->connection->error);
            }
        } catch (mysqli_sql_exception $e) {
            throw new RuntimeException('Error al hacer rollback en la transacción: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}