<?php

use RealsoftGt\OnneB\Data\Catalog;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\Mapping\CatalogMapper;

function onnebProduct(array $overrides = []): array
{
    return [
        'CODPROD' => '100',
        'CODPROD2' => '100',
        'DESPROD' => 'ACETAMINOFEN X 100 TABLETAS (MK)',
        'DESPROD2' => 'ACETAMINOFEN X 100 TABLETAS (MK)',
        'DESPROD3' => 'ACETAMINOFEN X 100 TABLETAS (MK)',
        'UXC' => 1,
        'COSTO' => '1.0000',
        'CODMARCA' => 1,
        'INVMINIMO' => 0,
        'INVMAXIMO' => 0,
        'EXENTO' => 0,
        'TIPOPROD' => 'P',
        ...$overrides,
    ];
}

function onnebPrice(string $unit, float $factor, float $price, float $cost = 5.0, string $codprod = '100'): array
{
    static $id = 0;

    return ['ID' => ++$id, 'CODPROD' => $codprod, 'CODMEDIDA' => $unit, 'EQUIVALE' => $factor, 'PRECIO' => $price, 'COSTO' => $cost];
}

function onnebCatalog(array $products, array $prices, array $brands = [1 => 'MK']): Catalog
{
    return new Catalog($products, $prices, $brands, [], 'snapshot', 'EMPRESA', '2026-01-15T08:00:00-06:00');
}

/**
 * @return array{product: array<string, mixed>, issues: list<string>}
 */
function mapOnnebProduct(array $product, array $prices): array
{
    $result = (new CatalogMapper)->map(onnebCatalog([$product], $prices));

    return ['product' => $result->products[0], 'issues' => array_column($result->issues, 'incidencia')];
}

it('usa como base la fila con factor 1 y conserva los demás factores', function () {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(onnebProduct(), [
        onnebPrice('CAJA', 100, 90, 50),
        onnebPrice('BLISTER', 10, 12, 5),
        onnebPrice('UNIDAD', 1, 2, 0.5),
    ]);

    expect($product['base_unit'])->toBe('UNIDAD')
        ->and($product['price'])->toBe(2.0)
        ->and($product['standard_cost'])->toBe(0.5)
        ->and(array_column($product['presentations'], 'name'))->toBe(['Unidad', 'Blíster x 10', 'Caja x 100'])
        ->and(array_column($product['presentations'], 'base_factor'))->toBe([1.0, 10.0, 100.0])
        ->and($product['presentations'][0]['price'])->toBeNull()
        ->and($product['presentations'][2]['price'])->toBe(90.0)
        ->and($issues)->toBe([]);
});

it('no inventa factores cuando varias filas declaran 1', function () {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(onnebProduct(), [
        onnebPrice('CAJA', 1, 218),
        onnebPrice('AMPOLLA', 1, 22),
    ]);

    [$base, $box] = $product['presentations'];

    expect($base['unit'])->toBe('AMPOLLA')
        ->and($base['is_base'])->toBeTrue()
        ->and($box['unit'])->toBe('CAJA')
        ->and($box['is_active'])->toBeFalse()
        ->and($box['price'])->toBe(218.0)
        ->and($issues)->toContain('base_ambigua');
});

it('toma el factor de UXC solo si los precios lo respaldan', function (float $boxPrice, bool $usesUnitsPerBox) {
    ['product' => $product] = mapOnnebProduct(onnebProduct(['UXC' => 24]), [
        onnebPrice('UNIDAD', 1, 3),
        onnebPrice('CAJA', 1, $boxPrice),
    ]);

    $box = $product['presentations'][1];

    expect($box['is_active'])->toBe($usesUnitsPerBox)
        ->and($box['base_factor'])->toBe($usesUnitsPerBox ? 24.0 : 1.0);
})->with([
    'coherente' => [65, true],
    'caja más cara por unidad que la base' => [120, false],
]);

it('deriva el costo base de la caja cuando el factor viene de UXC', function () {
    ['product' => $product] = mapOnnebProduct(onnebProduct(['UXC' => 24]), [
        onnebPrice('UNIDAD', 1, 3, 48),
        onnebPrice('CAJA', 1, 65, 48),
    ]);

    expect($product['standard_cost'])->toBe(2.0);
});

it('crea una UNIDAD no vendible cuando ninguna fila vale 1', function () {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(onnebProduct(), [
        onnebPrice('BLISTER', 10, 29, 9.1095),
        onnebPrice('CAJA', 100, 245, 91.095),
    ]);

    [$base, $blister, $box] = $product['presentations'];

    expect($product['base_unit'])->toBe('UNIDAD')
        ->and($product['price'])->toBe(2.9)
        ->and($product['standard_cost'])->toBe(0.911)
        ->and($base['is_sellable'])->toBeFalse()
        ->and($base['is_purchasable'])->toBeFalse()
        ->and($blister['base_factor'])->toBe(10.0)
        ->and($box['base_factor'])->toBe(100.0)
        ->and($issues)->toContain('base_implicita');
});

it('no duplica la unidad cuando UNIDAD declara un factor mayor que 1', function () {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(onnebProduct(), [
        onnebPrice('UNIDAD', 12, 149),
        onnebPrice('CAJA', 24, 280),
    ]);

    [$base, $box] = $product['presentations'];

    expect($base['unit'])->toBe('UNIDAD')
        ->and($base['base_factor'])->toBe(1.0)
        ->and($box['is_active'])->toBeFalse()
        ->and($issues)->toContain('factor_no_confiable');
});

it('inactiva un paquete más barato que la base', function () {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(onnebProduct(), [
        onnebPrice('BLISTER', 1, 10),
        onnebPrice('UNIDAD', 10, 2),
    ]);

    expect($product['presentations'][1]['is_active'])->toBeFalse()
        ->and($issues)->toContain('precio_invertido');
});

it('ignora costos de relleno y deriva el costo del paquete', function () {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(onnebProduct(), [
        onnebPrice('BLISTER', 1, 7, 0.1),
        onnebPrice('CAJA', 10, 55, 40),
    ]);

    expect($product['standard_cost'])->toBe(4.0)
        ->and($issues)->toContain('costo_derivado');
});

it('reporta cuando no hay ningún costo confiable', function () {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(onnebProduct(['COSTO' => '0.1000']), [
        onnebPrice('CAJA', 1, 55, 0.1),
    ]);

    expect($product['standard_cost'])->toBe(0.0)
        ->and($issues)->toContain('sin_costo');
});

it('no asigna a ningún producto un código de barras compartido', function () {
    $result = (new CatalogMapper)->map(onnebCatalog(
        [
            onnebProduct(['CODPROD' => '1', 'CODPROD2' => '7410001010800']),
            onnebProduct(['CODPROD' => '2', 'CODPROD2' => '7410001010800']),
        ],
        [onnebPrice('CAJA', 1, 10, 5, '1'), onnebPrice('CAJA', 1, 10, 5, '2')],
    ));

    expect(array_column($result->products, 'barcode'))->toBe([null, null])
        ->and(array_column($result->issues, 'incidencia'))->toBe(['barcode_compartido', 'barcode_compartido']);
});

it('resuelve el código de barras según CODPROD2', function (string $alternate, ?string $barcode, ?string $issue) {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(
        onnebProduct(['CODPROD2' => $alternate]),
        [onnebPrice('CAJA', 1, 10)],
    );

    expect($product['barcode'])->toBe($barcode)
        ->and($issues)->toBe($issue === null ? [] : [$issue]);
})->with([
    'igual al SKU' => ['100', null, null],
    'GTIN válido' => ['7410001010800', '7410001010800', null],
    'código interno único' => ['0000336', '0000336', 'barcode_no_gtin'],
]);

it('mapea un servicio sin presentaciones ni inventario', function () {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(onnebProduct(['TIPOPROD' => 'S']), [
        onnebPrice('UNIDAD', 1, 30),
        onnebPrice('CAJA', 2, 50),
    ]);

    expect($product['type'])->toBe('service')
        ->and($product['presentations'])->toBe([])
        ->and($product['price'])->toBe(30.0)
        ->and($issues)->toContain('servicio_con_varias_tarifas');
});

it('limpia la marca final del nombre y conserva el original', function () {
    ['product' => $product] = mapOnnebProduct(onnebProduct([
        'DESPROD' => 'DOLKEFLAM 20mg x10 TABLETASx (HEIDELG PHARMA)x',
        'DESPROD2' => 'DOLKEFLAM 20mg x10 TABLETASx (HEIDELG PHARMA)x',
        'DESPROD3' => 'DOLKEFLAM 20mg x10 TABLETASx (HEIDELG PHARMA)x',
    ]), [onnebPrice('CAJA', 1, 10)]);

    expect($product['name'])->toBe('DOLKEFLAM 20mg x10 TABLETASx (HEIDELG PHARMA)')
        ->and($product['description'])->toBe('DOLKEFLAM 20mg x10 TABLETASx (HEIDELG PHARMA)x');
});

it('aplica exención, marca genérica y límites de stock', function () {
    $result = (new CatalogMapper)->map(onnebCatalog(
        [onnebProduct(['EXENTO' => 1, 'CODMARCA' => 2, 'INVMINIMO' => 10, 'INVMAXIMO' => 5])],
        [onnebPrice('CAJA', 1, 10)],
        [2 => ' Varios '],
    ));

    expect($result->products[0])
        ->is_exempt->toBeTrue()
        ->brand->toBeNull()
        ->min_stock->toBe(10.0)
        ->max_stock->toBeNull();
});

it('crea la base en UNIDAD para un producto sin filas en PRECIOS', function () {
    ['product' => $product, 'issues' => $issues] = mapOnnebProduct(onnebProduct(), []);

    expect($product['base_unit'])->toBe('UNIDAD')
        ->and($product['price'])->toBeNull()
        ->and($product['presentations'])->toHaveCount(1)
        ->and($issues)->toContain('sin_presentaciones');
});

it('normaliza medidas con espacios y rechaza las desconocidas', function () {
    ['product' => $product] = mapOnnebProduct(onnebProduct(), [onnebPrice('RESMA ', 1, 10)]);

    expect($product['base_unit'])->toBe('RESMA');

    (new CatalogMapper)->map(onnebCatalog([onnebProduct()], [onnebPrice('BARRIL', 1, 10)], []));
})->throws(OnneBException::class, 'BARRIL');

it('tiene códigos de unidad únicos y cortos', function () {
    $codes = array_column(CatalogMapper::DEFAULT_UNITS, 0);

    expect($codes)->toHaveCount(count(array_unique($codes)))
        ->and(max(array_map('strlen', $codes)))->toBeLessThanOrEqual(10);
});

it('valida el dígito verificador de un GTIN', function (string $code, bool $valid) {
    expect(CatalogMapper::isValidGtin($code))->toBe($valid);
})->with([
    ['7410001010800', true],
    ['7410001010801', false],
    ['019006500179', true],
    ['abc', false],
]);
