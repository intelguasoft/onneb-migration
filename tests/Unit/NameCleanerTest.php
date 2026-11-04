<?php

use RealsoftGt\OnneB\Mapping\NameCleaner;

it('quita el laboratorio y el contenido del empaque', function (string $source, string $name, ?string $label, ?string $content) {
    $cleaned = (new NameCleaner)->clean($source);

    expect($cleaned->name)->toBe($name)
        ->and($cleaned->label)->toBe($label)
        ->and($cleaned->content === null ? null : $cleaned->content['display'].' '.($cleaned->content['plural'] ?? '-'))->toBe($content);
})->with([
    'caja y corchetes' => ['ACETAMINOFEN 500MG CAJA X 100 TABLETAS [MK]', 'ACETAMINOFEN 500MG', 'MK', '100 tabletas'],
    'paréntesis y x de OnneB' => ['CEFIXIMA 400 MG. X 30 CAPSULAS (WASHINGTON)X', 'CEFIXIMA 400 MG.', 'WASHINGTON', '30 cápsulas'],
    'x pegada al sustantivo' => ['DOLKEFLAM 20mg x10 TABLETASx (HEIDELG PHARMA)x', 'DOLKEFLAM 20mg', 'HEIDELG PHARMA', '10 tabletas'],
    'asterisco' => ['SARTAX A 20/5mg *30 TABLETAS (XAIKUS)x', 'SARTAX A 20/5mg', 'XAIKUS', '30 tabletas'],
    'número antes de la X' => ['ULTRA OMEGA 3 X 60 CAPSULAS [VITABIOTICS]', 'ULTRA OMEGA 3', 'VITABIOTICS', '60 cápsulas'],
    'unidades del caso Prodigy' => ['TIRAS REACTIVAS PRODIGY x 100 UNIDADES', 'TIRAS REACTIVAS PRODIGY', null, '100 unidades'],
    'promoción' => ['NEBISTAR 5 MG X 30+15 TABLETAS [DONOVAN]', 'NEBISTAR 5 MG', 'DONOVAN', '30+15 tabletas'],
    'empaque dentro de empaque' => ['PULMO CALCIO SABOR FRESA x25 BLISTER DE 8 TABLETAS [PROCAPS]', 'PULMO CALCIO SABOR FRESA', 'PROCAPS', '200 tabletas'],
    'sobres con cápsulas' => ['DOLO MAX FORTE DISPENSADOR X 25 SOBRES X 4 CAPSULAS (FARQUETSA)X', 'DOLO MAX FORTE', 'FARQUETSA', '100 cápsulas'],
    'conserva el tamaño de cada pieza' => ['REFORZADEC NIÑOS x25 AMPOLLAS DE 5ml (BIOVAL)', 'REFORZADEC NIÑOS 5ml', 'BIOVAL', '25 ampollas'],
    'conserva la forma farmacéutica' => ['PANADOL NIÑOS x100 TABLETAS MASTICABLES (GSK)x', 'PANADOL NIÑOS TABLETAS MASTICABLES', 'GSK', '100 tabletas'],
    'conteo sin sustantivo' => ['GUANTES KID GLOVES TALLA S x100 (AMBIDERM)x', 'GUANTES KID GLOVES TALLA S', 'AMBIDERM', '100 -'],
    'conserva el principio activo' => ['ETP 120 MG (ETORICOXIB) X 14 TABLETAS (PHARMADEL)', 'ETP 120 MG (ETORICOXIB)', 'PHARMADEL', '14 tabletas'],
    'tamaño con X' => ['SIMILAC NEO SURE EYEQ x370gr (ABBOTT)x', 'SIMILAC NEO SURE EYEQ 370gr', 'ABBOTT', null],
    'empaque al final' => ['SUERO MIXTO AL 5% 1000ml BOTE (BONIN)x', 'SUERO MIXTO AL 5% 1000ml', 'BONIN', null],
    'empaque antes del tamaño' => ['FLAMYGEL TUBO X 30 GR [UNIPHARM]', 'FLAMYGEL 30 GR', 'UNIPHARM', null],
    'medidas de dos lados' => ['VENDA DE GASA 6 X 10 YDS [MIGASA]', 'VENDA DE GASA 6 X 10 YDS', 'MIGASA', null],
    'pulgadas' => ['ESPARADRAPO 1" X 10 YDS COLOR BLANCO [HEALT MAX]', 'ESPARADRAPO 1" X 10 YDS COLOR BLANCO', 'HEALT MAX', null],
    'el empaque es el producto' => ['SOBRE BLANCO OFICIO', 'SOBRE BLANCO OFICIO', null, null],
    'sin caja' => ['MICROPORE COLOR BLANCO 1" X 10 YD SIN CAJA [NEXCARE]', 'MICROPORE COLOR BLANCO 1" X 10 YD SIN CAJA', 'NEXCARE', null],
    'medida de aguja' => ['JERINGA DESECHABLE 10 ML 21g x 1 1/2 x100 UNIDADES (CHANGZHOU)', 'JERINGA DESECHABLE 10 ML 21g x 1 1/2', 'CHANGZHOU', '100 unidades'],
    'sin nada que limpiar' => ['LORATADINA SUSPENSION 60 ML', 'LORATADINA SUSPENSION 60 ML', null, null],
]);

it('conserva el original si la limpieza no deja un nombre', function () {
    expect((new NameCleaner)->clean('X 100 TABLETAS [MK]')->name)->toBe('X 100 TABLETAS [MK]');
});
