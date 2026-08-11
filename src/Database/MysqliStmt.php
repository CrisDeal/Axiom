<?php

declare(strict_types=1);

namespace Axiom\Database;

use mysqli_stmt;
use mysqli_sql_exception;
use RuntimeException;
use Axiom\Contracts\Database\StatementInterface;

/**
 * Implementación de StatementInterface utilizando MySQLi.
 *
 * Envuelve un mysqli_stmt nativo, gestionando el enlace dinámico de 
 * parámetros por tipos y previniendo fugas de memoria.
 */
class MysqliStmt implements StatementInterface {

    /**
     * Crea un nuevo wrapper para el statement de Mysqli.
     * 
     * @param mysqli_stmt $stmt Instancia del statement preparado de MySQLi.
     */
    public function __construct(
        private mysqli_stmt $stmt
    ) {}

    /**
     * Enlaza parámetros a la consulta preparándolos por tipo de dato.
     * 
     * @param array<int|string, mixed> $params
     * @throws RuntimeException Si el enlace falla.
     */
    public function bind(array $params) : void {
        if(empty($params)) {
            return;
        }

        // PRECAUCIÓN: MySQLi solo entiende signos de interrogación (?), por lo que 
        // el orden estricto importa. Extraemos solo los valores numéricamente indexados 
        // por si el ORM nos envió un array asociativo.
        $values = array_values($params);

        $types = '';
        foreach($values as $param) {
            $types .= match(true) {
                is_int($param), is_bool($param) => 'i',
                is_float($param) => 'd', // 'd' de double
                default => 's', // 's' de string, cubre nulos y textos
            };
        }

        try {
            // Desempaquetamos los valores ordenados (...$values)
            if(!$this->stmt->bind_param($types, ...$values)) {
                throw new RuntimeException('Error al enlazar parámetros (Mysqli): ' . $this->stmt->error);
            }
        } catch (mysqli_sql_exception $e) {
            throw new RuntimeException('Excepción al enlazar parámetros: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Ejecuta la consulta en la base de datos.
     * 
     * @return bool
     * @throws RuntimeException Si la ejecución falla a nivel del driver.
     */
    public function execute() : bool {
        try {
            if(!$this->stmt->execute()) {
                throw new RuntimeException('Error al ejecutar consulta (Mysqli): ' . $this->stmt->error);
            }
            return true;
        } catch (mysqli_sql_exception $e) {
            throw new RuntimeException('Excepción al ejecutar consulta: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Devuelve todos los resultados. Úsalo solo para colecciones pequeñas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll() : array {
        $result = $this->stmt->get_result();
        
        // Operaciones como INSERT/UPDATE devuelven false al no generar tabla de resultados.
        if($result === false) {
            return [];
        }

        $data = $result->fetch_all(MYSQLI_ASSOC);
        $result->free(); // Liberamos memoria explícitamente en C/MySQL.
        
        return $data;
    }

    /**
     * Iterador de memoria eficiente para leer miles de registros.
     * 
     * @return \Generator<int, array<string, mixed>>
     */
    public function fetch() : \Generator {
        $result = $this->stmt->get_result();
        
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                yield $row;
            }
            $result->free();
        }
    }

    /**
     * Extrae de forma eficiente un único registro, sin cargar el resto.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne() : ?array {
        $result = $this->stmt->get_result();
        
        if($result === false) {
            return null;
        }

        // fetch_assoc devuelve el primer arreglo o null/false si está vacío
        $row = $result->fetch_assoc();
        $result->free();

        return $row ?: null;
    }

    /**
     * @return int
     */
    public function affectedRows() : int {
        return (int) $this->stmt->affected_rows;
    }
}