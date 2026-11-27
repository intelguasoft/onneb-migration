<?php

namespace RealsoftGt\OnneB\Data;

use RealsoftGt\OnneB\Support\CatalogTables;

/**
 * Catálogo de OnneB tal como viene del origen, con las columnas originales.
 */
final readonly class Catalog
{
    /**
     * @param  list<array<string, mixed>>  $products  Solo productos habilitados.
     * @param  list<array<string, mixed>>  $prices
     * @param  array<int|string, string>  $brands  CODMARCA => DESMARCA.
     * @param  array<string, float>  $stock  Saldo por código normalizado.
     * @param  string  $origin  Nombre del origen: `database`, `snapshot` u otro.
     * @param  string  $extractedAt  Fecha ISO 8601 de los datos.
     * @param  list<array<string, mixed>>  $suppliers  Filas de PROVEEDORES.
     * @param  list<array{CODPROD: string, CODPROV: int, COMPRAS: int, ULTIMA_COMPRA: string}>  $purchases  Compras por producto y proveedor.
     */
    public function __construct(
        public array $products,
        public array $prices,
        public array $brands,
        public array $stock,
        public string $origin,
        public string $empnit,
        public string $extractedAt,
        public array $suppliers = [],
        public array $purchases = [],
    ) {}

    /**
     * Único punto que interpreta las tablas: garantiza que todos los orígenes
     * entreguen exactamente el mismo catálogo.
     *
     * @param  array<string, list<array<string, mixed>>>  $tables
     */
    public static function fromTables(array $tables, string $origin, string $empnit, string $extractedAt): self
    {
        $stock = [];

        foreach ($tables['INVSALDO'] as $row) {
            $code = mb_strtoupper(trim((string) $row['CODPROD']));
            $stock[$code] = ($stock[$code] ?? 0) + (float) $row['SALDO'];
        }

        return new self(
            products: array_values(array_filter(
                $tables['PRODUCTOS'],
                fn (array $product): bool => mb_strtoupper(trim((string) $product['HABILITADO'])) === 'SI',
            )),
            prices: $tables['PRECIOS'],
            brands: array_column($tables['MARCAS'], 'DESMARCA', 'CODMARCA'),
            stock: $stock,
            origin: $origin,
            empnit: $empnit,
            extractedAt: $extractedAt,
            suppliers: $tables['PROVEEDORES'] ?? [],
            purchases: $tables[CatalogTables::PURCHASES] ?? [],
        );
    }
}
