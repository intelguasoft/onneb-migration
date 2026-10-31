<?php

namespace RealsoftGt\OnneB\Contracts;

use RealsoftGt\OnneB\Data\Catalog;

/**
 * Origen del catálogo. Todo origen entrega las mismas tablas de OnneB, de modo
 * que el mapeo no depende de dónde se leyeron.
 */
interface CatalogSource
{
    public function read(): Catalog;
}
