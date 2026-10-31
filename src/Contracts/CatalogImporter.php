<?php

namespace RealsoftGt\OnneB\Contracts;

use RealsoftGt\OnneB\Data\ImportResult;
use RealsoftGt\OnneB\Data\MappedCatalog;

/**
 * Destino del catálogo: cada aplicación lo implementa con sus propios modelos.
 *
 * Con `$dryRun` debe calcular el resultado sin escribir nada.
 */
interface CatalogImporter
{
    public function import(MappedCatalog $catalog, bool $dryRun): ImportResult;
}
