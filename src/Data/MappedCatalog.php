<?php

namespace RealsoftGt\OnneB\Data;

/**
 * Productos ya traducidos y las decisiones que requieren revisión.
 */
final readonly class MappedCatalog
{
    /**
     * @param  list<array<string, mixed>>  $products
     * @param  list<array{codprod: string, descripcion: string, incidencia: string, severidad: string, detalle: string}>  $issues
     */
    public function __construct(
        public array $products,
        public array $issues,
    ) {}
}
