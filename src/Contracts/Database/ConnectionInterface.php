<?php

declare(strict_types=1);

namespace Axiom\Contracts\Database;

/**
 * Define el contrato para las conexiones a la base de datos de Axiom.
 * 
 * Esta interfaz abstrae el controlador subyacente (PDO, mysqli, etc.),
 * permitiendo que el ORM se mantenga completamente desacoplado de 
 * cualquier implementación de bajo nivel.
 */
interface ConnectionInterface { 

    /**
     * Prepara una consulta SQL para su ejecución segura.
     * 
     * @param string $sql La consulta SQL a preparar.
     * @return StatementInterface
     */
    public function prepare(string $sql): StatementInterface;
 
    /**
     * Devuelve el último ID autogenerado insertado en la base de datos.
     * Soporta tanto enteros (AUTO_INCREMENT) como cadenas (para UUIDs).
     *
     * @return int|string
     */
    public function lastInsertId(): int|string;

    /**
     * Inicia una transacción en la base de datos.
     * 
     * Todas las consultas posteriores se ejecutarán dentro de esta transacción
     * hasta que se confirme con commit() o se revierta con rollback().
     *
     * @throws \RuntimeException Si la transacción no puede iniciarse.
     */
    public function beginTransaction(): void;

    /**
     * Confirma la transacción activa.
     * Persiste permanentemente todos los cambios realizados desde beginTransaction().
     *
     * @throws \RuntimeException Si no hay una transacción activa.
     */
    public function commit(): void;

    /**
     * Revierte la transacción activa.
     * Deshace todos los cambios realizados desde beginTransaction().
     *
     * @throws \RuntimeException Si no hay una transacción activa.
     */
    public function rollback(): void;
}