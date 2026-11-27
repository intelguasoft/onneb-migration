<?php

namespace RealsoftGt\OnneB\Data;

use RealsoftGt\OnneB\Mapping\SupplierMapper;

/**
 * Productos ya traducidos y las decisiones que requieren revisión.
 *
 * @phpstan-import-type MappedSupplier from SupplierMapper
 */
final readonly class MappedCatalog
{
    /**
     * @param  list<array<string, mixed>>  $products
     * @param  list<array{codprod: string, descripcion: string, incidencia: string, severidad: string, detalle: string}>  $issues
     * @param  list<MappedSupplier>  $suppliers  Proveedores traducidos, con lo que hay que revisar de cada uno en `review`.
     */
    public function __construct(
        public array $products,
        public array $issues,
        public array $suppliers = [],
    ) {}
}
