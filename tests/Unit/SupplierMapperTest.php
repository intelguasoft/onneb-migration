<?php

use RealsoftGt\OnneB\Data\Catalog;
use RealsoftGt\OnneB\Mapping\SupplierMapper;

function supplierRow(array $overrides = []): array
{
    return [
        'CODPROV' => 1, 'EMPRESA' => 'DROGUERIA', 'RAZONSOCIAL' => 'DROGUERIA, S.A.', 'DIRECCION' => '2 CALLE 34-16 ZONA 7',
        'TELEMPRESA' => '2439-4706', 'CONTACTO' => 'SN', 'TELCONTACTO' => '30 DÍAS CRÉDITO', 'NIT' => '576937-K', 'SALDO' => 0.0,
        ...$overrides,
    ];
}

/**
 * @param  list<array<string, mixed>>  $suppliers
 * @return list<array<string, mixed>>
 */
function mapSuppliers(array $suppliers): array
{
    return (new SupplierMapper)->map(new Catalog([], [], [], [], 'test', 'EMPRESA', '2026-11-26T00:00:00-06:00', $suppliers))['suppliers'];
}

it('traduce un proveedor con los campos que OnneB usa a su manera', function () {
    [$supplier] = mapSuppliers([supplierRow()]);

    expect($supplier)->toMatchArray([
        'code' => '1',
        'legal_name' => 'DROGUERIA, S.A.',
        'trade_name' => 'DROGUERIA',
        'tax_id' => '576937K',
        'phone' => '2439-4706',
        'mobile' => null,
        'contact' => null,
        'credit_days' => 30,
        'balance' => 0.0,
        'review' => [],
    ])->and($supplier['notes'])->toBe("Dirección: 2 CALLE 34-16 ZONA 7\nCondición en OnneB: 30 DÍAS CRÉDITO");
});

it('reconoce la condición de pago, el contacto y un teléfono donde vengan', function (array $row, ?int $days, ?string $phone, ?string $mobile, ?string $contact) {
    [$supplier] = mapSuppliers([supplierRow($row)]);

    expect($supplier['credit_days'])->toBe($days)
        ->and($supplier['phone'])->toBe($phone)
        ->and($supplier['mobile'])->toBe($mobile)
        ->and($supplier['contact'])->toBe($contact);
})->with([
    'contado' => [['TELCONTACTO' => 'CONTADO'], 0, '2439-4706', null, null],
    'crédito en el contacto' => [['TELCONTACTO' => 'SN', 'CONTACTO' => '60 DIAS CREDITO'], 60, '2439-4706', null, null],
    'celular y contacto' => [['TELCONTACTO' => '3000-5707', 'CONTACTO' => 'HUGO'], null, '2439-4706', '3000-5707', 'HUGO'],
    'nombre en el teléfono' => [['TELEMPRESA' => 'HUGO POGGIO'], 30, null, null, null],
]);

it('marca lo que hay que revisar de cada proveedor', function () {
    $suppliers = mapSuppliers([
        supplierRow(['CODPROV' => 1, 'NIT' => '100607128']),
        supplierRow(['CODPROV' => 2, 'NIT' => '10060712-8']),
        supplierRow(['CODPROV' => 3, 'NIT' => 'C/F', 'SALDO' => -18676.3, 'TELCONTACTO' => 'SN']),
        supplierRow(['CODPROV' => 4, 'NIT' => '12345678']),
    ]);

    expect(array_column($suppliers, 'review', 'code'))->toBe([
        '1' => ['nit_repetido'],
        '2' => ['nit_repetido'],
        '3' => ['proveedor_sin_nit', 'proveedor_con_saldo', 'credito_por_confirmar'],
        '4' => ['nit_invalido'],
    ])->and($suppliers[2]['tax_id'])->toBe('CF')
        ->and($suppliers[2]['balance'])->toBe(-18676.3);
});

it('valida el NIT de Guatemala con su dígito verificador', function (string $taxId, bool $valid) {
    expect(SupplierMapper::isValidTaxId(SupplierMapper::normalizeTaxId($taxId)))->toBe($valid);
})->with([
    ['576937-K', true],
    ['100607128', true],
    ['1234567-K', false],
    ['12345678', false],
    ['CF', true],
    ['ABC', false],
]);
