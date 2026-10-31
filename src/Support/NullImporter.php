<?php

namespace RealsoftGt\OnneB\Support;

use RealsoftGt\OnneB\Contracts\CatalogImporter;
use RealsoftGt\OnneB\Data\ImportResult;
use RealsoftGt\OnneB\Data\MappedCatalog;

/**
 * Destino que no escribe: permite revisar el catálogo sin aplicación de destino.
 */
final class NullImporter implements CatalogImporter
{
    public function import(MappedCatalog $catalog, bool $dryRun): ImportResult
    {
        $goods = array_filter($catalog->products, fn (array $product): bool => $product['type'] === 'good');

        return new ImportResult([
            'Productos' => count($catalog->products),
            'Bienes' => count($goods),
            'Servicios' => count($catalog->products) - count($goods),
            'Presentaciones' => array_sum(array_map(fn (array $product): int => count($product['presentations']), $catalog->products)),
            'Con impuesto' => count(array_filter($catalog->products, fn (array $product): bool => ! $product['is_exempt'])),
            'Exentos' => count(array_filter($catalog->products, fn (array $product): bool => $product['is_exempt'])),
        ], $catalog->issues);
    }
}
