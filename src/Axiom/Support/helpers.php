<?php
declare(strict_types=1);

use Axiom\Support\Debug;
use Axiom\Support\Str;

if (!function_exists('e')) {
    /**
     * Escapa HTML para prevenir ataques XSS.
     */
    function e(?string $value): string {
        return Str::escape($value);
    }
}

if (!function_exists('slug')) {
    /**
     * Genera un slug amigable para URLs.
     */
    function slug(string $value): string {
        return Str::slug($value);
    }
}

if (!function_exists('limit')) {
    /**
     * Trunca un texto a una longitud máxima
     */
    function limit(string $value): string {
        return Str::limit($value);
    }
}

if (!function_exists('dump')) {
    /**
     * Imprime el contenido de una variable sin detener la ejecución.
     */
    function dump(mixed $data): void {
        Debug::dump($data);
    }
}

if (!function_exists('dd')) {
    /**
     * Imprime el contenido de una variable y detiene la ejecución.
     */
    function dd(mixed $data): never {
        Debug::dd($data);
    }
}

if (!function_exists('ddd')) {
    /**
     * Imprime la variable con el stack trace y detiene la ejecución.
     */
    function ddd(mixed $data): never {
        Debug::ddd($data);
    }
}

if (!function_exists('measure')) {
    /**
     * Mide el tiempo de ejecución de un callable en milisegundos.
     */
    function measure(callable $fn): string {
        return Debug::measure($fn);
    }
}