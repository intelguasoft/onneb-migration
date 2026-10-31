<?php

namespace RealsoftGt\OnneB\Sources;

use RealsoftGt\OnneB\Contracts\CatalogSource;
use RealsoftGt\OnneB\Data\Catalog;

/**
 * Lee el catálogo directamente de SQL Server. Requiere pdo_sqlsrv y acceso de
 * red al servidor de OnneB.
 */
class DatabaseSource implements CatalogSource
{
    public function __construct(private readonly TableReader $reader) {}

    public function read(): Catalog
    {
        $extraction = $this->reader->read();

        return Catalog::fromTables($extraction['tables'], 'database', $extraction['empnit'], now()->toIso8601String());
    }
}
