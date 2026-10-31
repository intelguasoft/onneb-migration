<?php

namespace RealsoftGt\OnneB\Support;

/**
 * Un catálogo completo no cabe en los 128M que trae PHP por defecto.
 */
class MemoryLimit
{
    /**
     * Sube el límite a onneb.memory_limit, sin reducir nunca uno mayor.
     */
    public static function raise(): void
    {
        $limit = config('onneb.memory_limit');
        $current = ini_parse_quantity((string) ini_get('memory_limit'));

        if (filled($limit) && $current !== -1 && $current < ini_parse_quantity($limit)) {
            ini_set('memory_limit', $limit);
        }
    }
}
