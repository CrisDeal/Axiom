<?php

declare(strict_types=1);

namespace Axiom\Contracts\Database;

/**
 * Representa una sentencia de base de datos preparada.
 * 
 * Esta interfaz estandariza la vinculación de parámetros (evitando inyecciones SQL), 
 * la ejecución de consultas y la recuperación flexible de los resultados.
 */
interface StatementInterface {

    /**
     * Vincula un arreglo de parámetros a la sentencia preparada.
     * 
     * @param array<int|string, mixed> $params Arreglo asociativo o indexado de valores.
     * @return void
     */
    public function bind(array $params) : void;

    /**
     * Ejecuta la sentencia preparada en la base de datos.
     * 
     * REGLA DE IMPLEMENTACIÓN: Si la consulta falla (ej. error de sintaxis SQL 
     * o restricción de llave foránea), la implementación DEBE lanzar una 
     * excepción (ej. Axiom\Exceptions\DatabaseException) en lugar de fallar 
     * silenciosamente devolviendo false.
     * 
     * @return bool True en caso de ejecución exitosa.
     */
    public function execute() : bool;

    /**
     * Devuelve todas las filas de la sentencia ejecutada como un arreglo.
     * 
     * ADVERTENCIA DE MEMORIA: Úsalo solo para conjuntos de resultados pequeños 
     * o paginados. Si esperas miles de registros, utiliza fetch() para iterar 
     * con un Generador y evitar agotar la memoria de PHP.
     * 
     * @return array<int, array<string, mixed>> Arreglo de registros asociativos.
     */
    public function fetchAll() : array;

    /**
     * Iterador eficiente para conjuntos de resultados masivos.
     * 
     * Devuelve un registro a la vez manteniendo el consumo de memoria al mínimo, 
     * sin importar si la consulta devuelve 10 o 10,000 registros.
     * 
     * @return \Generator<int, array<string, mixed>>
     */
    public function fetch() : \Generator;

    /**
     * Devuelve la primera fila del conjunto de resultados.
     * 
     * @return array<string, mixed>|null El registro asociativo o null si está vacío.
     */
    public function fetchOne() : ?array;

    /**
     * Devuelve el número de filas afectadas por la última consulta 
     * (indispensable para validar operaciones UPDATE, DELETE o INSERT).
     * 
     * @return int Cantidad de filas modificadas.
     */
    public function affectedRows() : int;
}

// Consideraciones:

// Manejo de memoria en fetchAll(): Si una consulta devuelve 10,000 registros, fetchAll() cargará todos en un solo array, lo que podría agotar la memoria de PHP. Para implementaciones futuras, podrías considerar agregar un método como cursor() o fetch() que devuelva un Generator para iterar resultados uno por uno de manera eficiente.

// Manejo de errores en execute(): El método devuelve un bool. ¿Qué sucede si la consulta falla por un error de sintaxis SQL o una restricción de llave foránea? Sería ideal documentar (o definir en la interfaz) si la implementación debe lanzar una excepción personalizada (ej. DatabaseException) o simplemente devolver false.