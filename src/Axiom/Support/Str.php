<?php
declare(strict_types=1);

namespace Axiom\Support;

/**
 * Str
 *
 * Utilidades para la manipulación y formateo de cadenas de texto.
 */
class Str {

    /**
     * Escapa HTML para prevenir ataques XSS.
     *
     * @param string|null $value Valor a escapar.
     * @return string
     */
    public static function escape(?string $value): string {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Convierte un texto en un slug amigable para URLs.
     * Ejemplo: "Mi Título Genial!" -> "mi-titulo-genial"
     */
    public static function slug(string $title, string $separator = '-'): string {
        // Convierte a minúsculas, reemplaza espacios y elimina caracteres especiales
        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9]+/i', $separator, $title);
        return trim($title, $separator);
    }

    /**
     * Trunca un texto a una longitud máxima, añadiendo "..." al final.
     * Útil para previsualizaciones de artículos en vistas.
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string {
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . $end;
    }
    
    // Aquí puedes agregar más a futuro: camelCase(), snake_case(), random(), etc.
}