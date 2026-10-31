<?php

namespace RealsoftGt\OnneB\Mapping;

use RealsoftGt\OnneB\Data\Catalog;
use RealsoftGt\OnneB\Data\MappedCatalog;
use RealsoftGt\OnneB\Exceptions\OnneBException;

/**
 * Traduce el catálogo de OnneB a un modelo de productos con presentaciones,
 * sin tocar ninguna base.
 *
 * Reglas de presentaciones (PRECIOS.EQUIVALE = unidades base por presentación):
 *  - Una fila con factor 1: es la base y las demás conservan su factor.
 *  - Varias filas con factor 1: la de menor precio es la base. El factor real
 *    de las otras no existe en OnneB, así que quedan inactivas para revisión,
 *    salvo una CAJA sobre base UNIDAD cuyo UXC sea coherente con los precios.
 *  - Ninguna fila con factor 1: la base es una UNIDAD implícita no vendible,
 *    porque INVSALDO cuenta en esa unidad.
 *
 * Nada se descarta en silencio: cada decisión dudosa queda como incidencia.
 */
class CatalogMapper
{
    /** Por debajo de este costo, la fila de OnneB es un valor de relleno. */
    private const PLACEHOLDER_COST = 0.10;

    /** Un paquete no debería costar por unidad más que esto sobre la base. */
    private const MAX_UNIT_PRICE_RATIO = 1.5;

    private const BASE_UNIT = 'UNIDAD';

    /** Severidad de cada incidencia; las de `revision` requieren a una persona. */
    public const ISSUES = [
        'base_ambigua' => 'revision',
        'base_implicita' => 'revision',
        'factor_no_confiable' => 'revision',
        'factor_por_uxc' => 'revision',
        'precio_invertido' => 'revision',
        'precio_incoherente' => 'revision',
        'sin_precio' => 'revision',
        'sin_presentaciones' => 'revision',
        'costo_mayor_precio' => 'revision',
        'sin_costo' => 'revision',
        'barcode_compartido' => 'revision',
        'barcode_en_uso' => 'revision',
        'servicio_con_varias_tarifas' => 'revision',
        'sku_duplicado' => 'revision',
        'costo_derivado' => 'informativo',
        'barcode_no_gtin' => 'informativo',
        'stock_maximo_invalido' => 'informativo',
    ];

    /** CODMEDIDA de OnneB => [código, nombre] de la unidad en el destino. */
    public const DEFAULT_UNITS = [
        'UNIDAD' => ['UND', 'Unidad'],
        'CAJA' => ['CAJ', 'Caja'],
        'FRASCO' => ['FRA', 'Frasco'],
        'TUBO' => ['TUB', 'Tubo'],
        'BLISTER' => ['BLI', 'Blíster'],
        'BLISTERX4' => ['BLI4', 'Blíster x 4'],
        'BOTE' => ['BOT', 'Bote'],
        'BOTELLA' => ['BTL', 'Botella'],
        'PAQUETE' => ['PAQ', 'Paquete'],
        'GOTERO' => ['GOT', 'Gotero'],
        'GOTAS' => ['GTS', 'Gotas'],
        'AMPOLLA' => ['AMP', 'Ampolla'],
        'LATA' => ['LAT', 'Lata'],
        'KIT' => ['KIT', 'Kit'],
        'SOBRE' => ['SOB', 'Sobre'],
        'ROLLO' => ['ROL', 'Rollo'],
        'TARRO' => ['TAR', 'Tarro'],
        'BOLSA' => ['BOL', 'Bolsa'],
        'PAR' => ['PAR', 'Par'],
        'SPRAY' => ['SPR', 'Spray'],
        'DOCENA' => ['DOC', 'Docena'],
        'MEDIA DOCENA' => ['MDOC', 'Media docena'],
        'CIENTO' => ['CTO', 'Ciento'],
        'MEDIO CIENTO' => ['MCTO', 'Medio ciento'],
        'CARTERITA' => ['CAR', 'Carterita'],
        'COLIRIO' => ['COL', 'Colirio'],
        'VIAL' => ['VIA', 'Vial'],
        'GALON' => ['GAL', 'Galón'],
        'FARDO' => ['FAR', 'Fardo'],
        'PANITA' => ['PAN', 'Panita'],
        'DISPENSADOR' => ['DIS', 'Dispensador'],
        'RESMA' => ['RES', 'Resma'],
        'LIBRA' => ['LB', 'Libra'],
        'MEDIA LIBRA' => ['MLB', 'Media libra'],
        'TIRA' => ['TIR', 'Tira'],
        'ATADO' => ['ATA', 'Atado'],
        'POMADA' => ['POM', 'Pomada'],
        'OVULO' => ['OVU', 'Óvulo'],
        'SUPOSITORIO' => ['SUP', 'Supositorio'],
        '2PACK' => ['2PK', '2 pack'],
        '3XQUETZAL' => ['3XQ', '3 x quetzal'],
        'FRASCO100ML' => ['FRA100', 'Frasco 100 mL'],
        'FRASCO250ML' => ['FRA250', 'Frasco 250 mL'],
        'FRASCO500ML' => ['FRA500', 'Frasco 500 mL'],
        'FRASCO1000ML' => ['FRA1000', 'Frasco 1000 mL'],
        '10ML' => ['10ML', '10 mL'],
        '15ML' => ['15ML', '15 mL'],
        '20ML' => ['20ML', '20 mL'],
        '25ML' => ['25ML', '25 mL'],
        '30ML' => ['30ML', '30 mL'],
        '35ML' => ['35ML', '35 mL'],
        '40ML' => ['40ML', '40 mL'],
    ];

    /** @var array<string, array{0: string, 1: string}> */
    private readonly array $units;

    /** @var list<array{codprod: string, descripcion: string, incidencia: string, severidad: string, detalle: string}> */
    private array $issues = [];

    /**
     * @param  array<string, array{0: string, 1: string}>  $extraUnits  Medidas propias de la instalación.
     * @param  list<string>  $genericBrands  Marcas que no identifican a un fabricante.
     */
    public function __construct(array $extraUnits = [], private readonly array $genericBrands = ['VARIOS'])
    {
        $this->units = $extraUnits + self::DEFAULT_UNITS;
    }

    public function map(Catalog $catalog): MappedCatalog
    {
        $this->issues = [];

        $products = $catalog->products;
        $prices = $catalog->prices;
        $brands = $catalog->brands;

        $pricesByProduct = $this->groupPrices($prices);
        $barcodeOwners = $this->barcodeOwners($products);
        $skus = array_flip(array_map(fn (array $product): string => $this->upperTrim($product['CODPROD']), $products));

        $mapped = [];
        $seen = [];

        foreach ($products as $product) {
            $sku = trim((string) $product['CODPROD']);
            $key = mb_strtoupper($sku);

            if (isset($seen[$key])) {
                $this->issue($product, 'sku_duplicado', "El código {$sku} se repite tras normalizarlo; se conservó el primero.");

                continue;
            }

            $seen[$key] = true;
            $rows = $pricesByProduct[$key] ?? [];

            $mapped[] = $this->isService($product)
                ? $this->mapService($product, $rows, $brands, $barcodeOwners, $skus)
                : $this->mapGood($product, $rows, $brands, $barcodeOwners, $skus);
        }

        return new MappedCatalog($mapped, $this->issues);
    }

    /**
     * Código de medida normalizado; `RESMA ` y `RESMA` son la misma unidad.
     */
    public static function normalizeUnit(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public static function isValidGtin(string $code): bool
    {
        if (! preg_match('/^(\d{8}|\d{12,14})$/', $code)) {
            return false;
        }

        $digits = array_reverse(str_split($code));
        $sum = 0;

        foreach ($digits as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }

    /**
     * @param  list<array<string, mixed>>  $prices
     * @return array<string, list<array{id: int, unit: string, factor: float, price: float, cost: float}>>
     */
    private function groupPrices(array $prices): array
    {
        $grouped = [];
        $unknown = [];

        foreach ($prices as $row) {
            $unit = self::normalizeUnit((string) $row['CODMEDIDA']);

            if (! isset($this->units[$unit])) {
                $unknown[$unit] = true;

                continue;
            }

            $grouped[$this->upperTrim($row['CODPROD'])][] = [
                'id' => (int) $row['ID'],
                'unit' => $unit,
                'factor' => (float) $row['EQUIVALE'],
                'price' => (float) $row['PRECIO'],
                'cost' => (float) $row['COSTO'],
            ];
        }

        if ($unknown !== []) {
            throw new OnneBException('Medidas de OnneB sin equivalencia, agréguelas en onneb.mapping.extra_units: '.implode(', ', array_keys($unknown)));
        }

        foreach ($grouped as &$rows) {
            usort($rows, fn (array $a, array $b): int => [$a['factor'], $a['price'] <= 0, $a['price'], $a['id']]
                <=> [$b['factor'], $b['price'] <= 0, $b['price'], $b['id']]);
        }

        return $grouped;
    }

    /**
     * Productos que declaran cada CODPROD2. Un código compartido no sirve:
     * el POS tomaría el primero que encuentre.
     *
     * @param  list<array<string, mixed>>  $products
     * @return array<string, int>
     */
    private function barcodeOwners(array $products): array
    {
        $owners = [];

        foreach ($products as $product) {
            $code = $this->upperTrim($product['CODPROD2'] ?? '');

            if ($code !== '' && $code !== $this->upperTrim($product['CODPROD'])) {
                $owners[$code] = ($owners[$code] ?? 0) + 1;
            }
        }

        return $owners;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int|string, string>  $brands
     * @param  array<string, int>  $barcodeOwners
     * @param  array<string, int>  $skus
     * @return array<string, mixed>
     */
    private function mapGood(array $product, array $rows, array $brands, array $barcodeOwners, array $skus): array
    {
        if ($rows === []) {
            $this->issue($product, 'sin_presentaciones', 'No tiene filas en PRECIOS: se creó solo la base en UNIDAD, sin precio.');
            $presentations = [$this->presentation(self::BASE_UNIT, 1.0, null, true)];
            $baseUnitPrice = null;
        } else {
            [$presentations, $baseUnitPrice] = $this->resolvePresentations($product, $rows);
        }

        $base = collect($presentations)->firstWhere('is_base', true);
        $cost = $this->baseCost($product, $presentations);

        if ($baseUnitPrice === null && $rows !== []) {
            $this->issue($product, 'sin_precio', 'La presentación base tiene PRECIO en 0.');
        }

        if ($baseUnitPrice !== null && $cost > $baseUnitPrice) {
            $this->issue($product, 'costo_mayor_precio', "Costo base {$cost} mayor que el precio base {$baseUnitPrice}.");
        }

        [$minStock, $maxStock] = $this->stockLimits($product);

        return [
            ...$this->commonFields($product, $brands, $barcodeOwners, $skus),
            'type' => 'good',
            'base_unit' => $base['unit'],
            'standard_cost' => $cost,
            'price' => $baseUnitPrice,
            'min_stock' => $minStock,
            'max_stock' => $maxStock,
            'presentations' => array_map(function (array $presentation): array {
                unset($presentation['source_cost']);

                return $presentation;
            }, $presentations),
        ];
    }

    /**
     * Un servicio no maneja presentaciones: conserva una sola tarifa.
     *
     * @param  array<string, mixed>  $product
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int|string, string>  $brands
     * @param  array<string, int>  $barcodeOwners
     * @param  array<string, int>  $skus
     * @return array<string, mixed>
     */
    private function mapService(array $product, array $rows, array $brands, array $barcodeOwners, array $skus): array
    {
        $tariff = $rows[0] ?? null;

        if (count($rows) > 1) {
            $tariffs = collect($rows)->map(fn (array $row): string => "{$row['unit']}={$row['price']}")->join(', ');
            $this->issue($product, 'servicio_con_varias_tarifas', "Se conservó {$tariff['unit']}. Tarifas en OnneB: {$tariffs}.");
        }

        if (($tariff['price'] ?? 0) <= 0) {
            $this->issue($product, 'sin_precio', 'El servicio no tiene PRECIO en OnneB.');
        }

        return [
            ...$this->commonFields($product, $brands, $barcodeOwners, $skus),
            'type' => 'service',
            'base_unit' => self::BASE_UNIT,
            'standard_cost' => round(max(0, (float) $product['COSTO']), 4),
            'price' => ($tariff['price'] ?? 0) > 0 ? $tariff['price'] : null,
            'min_stock' => null,
            'max_stock' => null,
            'presentations' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: ?float}
     */
    private function resolvePresentations(array $product, array $rows): array
    {
        $minFactor = $rows[0]['factor'];

        if ($minFactor > 1) {
            return $this->resolveWithoutBaseRow($product, $rows);
        }

        $base = $rows[0];
        $basePrice = $base['price'] > 0 ? $base['price'] : null;
        $sameFactor = array_filter(array_slice($rows, 1), fn (array $row): bool => $row['factor'] === $minFactor);

        if ($sameFactor !== []) {
            $this->issue($product, 'base_ambigua', 'Varias presentaciones declaran factor 1; se usó como base '.$base['unit'].', la de menor precio.');
        }

        $presentations = [$this->presentation($base['unit'], 1.0, null, true, cost: $base['cost'])];

        foreach (array_slice($rows, 1) as $row) {
            $presentation = $row['factor'] === $minFactor
                ? $this->resolveAmbiguousRow($product, $row, $base, $basePrice)
                : $this->resolveRow($product, $row, $basePrice);

            // Con factor resuelto por UXC, el costo de la base era el de la caja.
            if ($row['factor'] === $minFactor && $presentation['is_active']) {
                $presentations[0]['source_cost'] = null;
            }

            $presentations[] = $presentation;
        }

        return [$presentations, $basePrice];
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: ?float}
     */
    private function resolveWithoutBaseRow(array $product, array $rows): array
    {
        $unitRow = collect($rows)->firstWhere('unit', self::BASE_UNIT);

        if ($unitRow !== null) {
            $this->issue($product, 'factor_no_confiable', "UNIDAD declara factor {$unitRow['factor']}: se dejó como base con factor 1 y las demás presentaciones inactivas.");

            $basePrice = $unitRow['price'] > 0 ? $unitRow['price'] : null;
            $presentations = [$this->presentation(self::BASE_UNIT, 1.0, null, true, cost: $unitRow['cost'])];

            foreach ($rows as $row) {
                if ($row['id'] !== $unitRow['id']) {
                    $presentations[] = $this->presentation($row['unit'], $row['factor'], $row['price'], false, active: false, cost: $row['cost']);
                }
            }

            return [$presentations, $basePrice];
        }

        $smallest = $rows[0];
        $this->issue($product, 'base_implicita', "Ninguna presentación vale 1; se creó UNIDAD no vendible. La menor es {$smallest['unit']} x {$smallest['factor']}.");

        $presentations = [$this->presentation(self::BASE_UNIT, 1.0, null, true, sellable: false, purchasable: false)];

        foreach ($rows as $row) {
            $presentations[] = $this->presentation($row['unit'], $row['factor'], $row['price'] > 0 ? $row['price'] : null, false, cost: $row['cost']);
        }

        return [
            $presentations,
            $smallest['price'] > 0 ? round($smallest['price'] / $smallest['factor'], 4) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function resolveAmbiguousRow(array $product, array $row, array $base, ?float $basePrice): array
    {
        $unitsPerBox = (float) $product['UXC'];
        $isBoxOverUnit = $row['unit'] === 'CAJA' && $base['unit'] === self::BASE_UNIT && $unitsPerBox > 1;

        if ($isBoxOverUnit && $basePrice !== null && $row['price'] > $basePrice && $basePrice >= $row['price'] / $unitsPerBox) {
            $this->issue($product, 'factor_por_uxc', "CAJA toma su factor de UXC ({$unitsPerBox}) porque OnneB declara 1.");

            return $this->presentation('CAJA', $unitsPerBox, $row['price'], false, cost: $row['cost']);
        }

        return $this->presentation($row['unit'], 1.0, $row['price'] > 0 ? $row['price'] : null, false, active: false);
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function resolveRow(array $product, array $row, ?float $basePrice): array
    {
        $active = true;

        if ($basePrice !== null && $row['price'] > 0) {
            if ($row['price'] < $basePrice) {
                $active = false;
                $this->issue($product, 'precio_invertido', "{$row['unit']} x {$row['factor']} vale {$row['price']}, menos que la base ({$basePrice}): quedó inactiva.");
            } elseif ($basePrice * self::MAX_UNIT_PRICE_RATIO < $row['price'] / $row['factor']) {
                $this->issue($product, 'precio_incoherente', "{$row['unit']} x {$row['factor']} vale {$row['price']}: sale más cara por unidad que la base ({$basePrice}).");
            }
        }

        return $this->presentation($row['unit'], $row['factor'], $row['price'] > 0 ? $row['price'] : null, false, active: $active, cost: $row['cost']);
    }

    /**
     * Costo por unidad base. La fila base manda; si trae relleno, se deriva
     * del paquete más grande, cuyo costo es el que OnneB actualiza al comprar.
     *
     * @param  array<string, mixed>  $product
     * @param  list<array<string, mixed>>  $presentations
     */
    private function baseCost(array $product, array $presentations): float
    {
        $ordered = collect($presentations)
            ->filter(fn (array $presentation): bool => $presentation['is_active'])
            ->sortByDesc(fn (array $presentation): array => [$presentation['is_base'], $presentation['base_factor']]);

        foreach ($ordered as $presentation) {
            if (($presentation['source_cost'] ?? 0) > self::PLACEHOLDER_COST) {
                if (! $presentation['is_base']) {
                    $this->issue($product, 'costo_derivado', "Costo derivado de {$presentation['unit']} x {$presentation['base_factor']}.");
                }

                return round($presentation['source_cost'] / $presentation['base_factor'], 4);
            }
        }

        $productCost = (float) $product['COSTO'];

        if ($productCost > self::PLACEHOLDER_COST) {
            $this->issue($product, 'costo_derivado', 'Costo tomado de PRODUCTOS.COSTO.');

            return round($productCost, 4);
        }

        $this->issue($product, 'sin_costo', 'OnneB solo tiene costos de relleno para este producto.');

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentation(
        string $unit,
        float $factor,
        ?float $price,
        bool $isBase,
        bool $active = true,
        bool $sellable = true,
        bool $purchasable = true,
        ?float $cost = null,
    ): array {
        $unitName = $this->units[$unit][1];

        return [
            'unit' => $unit,
            'name' => $factor > 1 ? "{$unitName} x ".rtrim(rtrim(number_format($factor, 6, '.', ''), '0'), '.') : $unitName,
            'base_factor' => $factor,
            // La base no lleva precio propio: usa el del producto.
            'price' => $isBase ? null : $price,
            'is_base' => $isBase,
            'is_purchasable' => $purchasable,
            'is_sellable' => $sellable,
            'is_active' => $active,
            'source_cost' => $cost,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<int|string, string>  $brands
     * @param  array<string, int>  $barcodeOwners
     * @param  array<string, int>  $skus
     * @return array<string, mixed>
     */
    private function commonFields(array $product, array $brands, array $barcodeOwners, array $skus): array
    {
        $rawName = $this->singleLine((string) $product['DESPROD']);
        $name = $this->cleanName($rawName);
        $alternative = collect([$product['DESPROD2'] ?? null, $product['DESPROD3'] ?? null])
            ->map(fn (?string $value): string => $this->singleLine((string) $value))
            ->first(fn (string $value): bool => $value !== '' && $value !== $rawName);

        return [
            'sku' => trim((string) $product['CODPROD']),
            'barcode' => $this->barcode($product, $barcodeOwners, $skus),
            'name' => $name,
            // Conserva el texto original cuando el nombre se limpió.
            'description' => $alternative ?? ($name !== $rawName ? $rawName : null),
            'brand' => $this->brand($product, $brands),
            'is_exempt' => (int) $product['EXENTO'] === 1,
        ];
    }

    /**
     * Quita la marca `x` que OnneB deja tras el laboratorio: `(HERSIL)x`.
     */
    private function cleanName(string $name): string
    {
        return preg_replace('/([)\]])\s*x$/iu', '$1', $name) ?? $name;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, int>  $barcodeOwners
     * @param  array<string, int>  $skus
     */
    private function barcode(array $product, array $barcodeOwners, array $skus): ?string
    {
        $code = trim((string) ($product['CODPROD2'] ?? ''));
        $key = mb_strtoupper($code);

        // El SKU ya identifica al producto: repetirlo como barcode no aporta nada.
        if ($code === '' || $key === $this->upperTrim($product['CODPROD'])) {
            return null;
        }

        if (($barcodeOwners[$key] ?? 0) > 1 || isset($skus[$key])) {
            $this->issue($product, 'barcode_compartido', "CODPROD2 {$code} también identifica a otro producto: no se migró.");

            return null;
        }

        if (! self::isValidGtin($code)) {
            $this->issue($product, 'barcode_no_gtin', "CODPROD2 {$code} no es un GTIN válido; se conservó porque es único.");
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<int|string, string>  $brands
     */
    private function brand(array $product, array $brands): ?string
    {
        $brand = $this->singleLine((string) ($brands[$product['CODMARCA']] ?? ''));

        return $brand === '' || in_array(mb_strtoupper($brand), $this->genericBrands, true) ? null : $brand;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{0: ?float, 1: ?float}
     */
    private function stockLimits(array $product): array
    {
        $min = (float) $product['INVMINIMO'];
        $max = (float) $product['INVMAXIMO'];
        $min = $min > 0 ? $min : null;
        $max = $max > 0 ? $max : null;

        if ($min !== null && $max !== null && $min > $max) {
            $this->issue($product, 'stock_maximo_invalido', "INVMAXIMO {$max} menor que INVMINIMO {$min}: se omitió el máximo.");
            $max = null;
        }

        return [$min, $max];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function isService(array $product): bool
    {
        return mb_strtoupper(trim((string) $product['TIPOPROD'])) === 'S';
    }

    private function upperTrim(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function singleLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function issue(array $product, string $code, string $detail): void
    {
        $this->issues[] = [
            'codprod' => trim((string) $product['CODPROD']),
            'descripcion' => $this->singleLine((string) $product['DESPROD']),
            'incidencia' => $code,
            'severidad' => self::ISSUES[$code],
            'detalle' => $detail,
        ];
    }
}
