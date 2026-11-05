<?php

use RealsoftGt\OnneB\Mapping\NameCleaner;

it('quita el laboratorio y el contenido del empaque', function (string $source, string $name, ?string $label, ?string $content) {
    $cleaned = (new NameCleaner)->clean($source);

    expect($cleaned->name)->toBe($name)
        ->and($cleaned->label)->toBe($label)
        ->and($cleaned->content === null ? null : $cleaned->content['display'].' '.($cleaned->content['plural'] ?? '-'))->toBe($content);
})->with([
    'caja y corchetes' => ['ACETAMINOFEN 500MG CAJA X 100 TABLETAS [MK]', 'ACETAMINOFEN 500 mg', 'MK', '100 tabletas'],
    'paréntesis y x de OnneB' => ['CEFIXIMA 400 MG. X 30 CAPSULAS (WASHINGTON)X', 'CEFIXIMA 400 mg', 'WASHINGTON', '30 cápsulas'],
    'x pegada al sustantivo' => ['DOLKEFLAM 20mg x10 TABLETASx (HEIDELG PHARMA)x', 'DOLKEFLAM 20 mg', 'HEIDELG PHARMA', '10 tabletas'],
    'asterisco' => ['SARTAX A 20/5mg *30 TABLETAS (XAIKUS)x', 'SARTAX A 20/5 mg', 'XAIKUS', '30 tabletas'],
    'número antes de la X' => ['ULTRA OMEGA 3 X 60 CAPSULAS [VITABIOTICS]', 'ULTRA OMEGA 3', 'VITABIOTICS', '60 cápsulas'],
    'unidades del caso Prodigy' => ['TIRAS REACTIVAS PRODIGY x 100 UNIDADES', 'TIRAS REACTIVAS PRODIGY', null, '100 unidades'],
    'promoción' => ['NEBISTAR 5 MG X 30+15 TABLETAS [DONOVAN]', 'NEBISTAR 5 mg', 'DONOVAN', '30+15 tabletas'],
    'empaque dentro de empaque' => ['PULMO CALCIO SABOR FRESA x25 BLISTER DE 8 TABLETAS [PROCAPS]', 'PULMO CALCIO SABOR FRESA', 'PROCAPS', '200 tabletas'],
    'sobres con cápsulas' => ['DOLO MAX FORTE DISPENSADOR X 25 SOBRES X 4 CAPSULAS (FARQUETSA)X', 'DOLO MAX FORTE', 'FARQUETSA', '100 cápsulas'],
    'conserva el tamaño de cada pieza' => ['REFORZADEC NIÑOS x25 AMPOLLAS DE 5ml (BIOVAL)', 'REFORZADEC NIÑOS 5 mL', 'BIOVAL', '25 ampollas'],
    'conserva la forma farmacéutica' => ['PANADOL NIÑOS x100 TABLETAS MASTICABLES (GSK)x', 'PANADOL NIÑOS TABLETAS MASTICABLES', 'GSK', '100 tabletas'],
    'conteo sin sustantivo' => ['GUANTES KID GLOVES TALLA S x100 (AMBIDERM)x', 'GUANTES KID GLOVES TALLA S', 'AMBIDERM', '100 -'],
    'conserva el principio activo' => ['ETP 120 MG (ETORICOXIB) X 14 TABLETAS (PHARMADEL)', 'ETP 120 mg (ETORICOXIB)', 'PHARMADEL', '14 tabletas'],
    'tamaño con X' => ['SIMILAC NEO SURE EYEQ x370gr (ABBOTT)x', 'SIMILAC NEO SURE EYEQ 370 g', 'ABBOTT', null],
    'empaque al final' => ['SUERO MIXTO AL 5% 1000ml BOTE (BONIN)x', 'SUERO MIXTO AL 5% 1000 mL', 'BONIN', null],
    'empaque antes del tamaño' => ['FLAMYGEL TUBO X 30 GR [UNIPHARM]', 'FLAMYGEL 30 g', 'UNIPHARM', null],
    'medidas de dos lados' => ['VENDA DE GASA 6 X 10 YDS [MIGASA]', 'VENDA DE GASA 6 X 10 yd', 'MIGASA', null],
    'pulgadas' => ['ESPARADRAPO 1" X 10 YDS COLOR BLANCO [HEALT MAX]', 'ESPARADRAPO 1" X 10 yd COLOR BLANCO', 'HEALT MAX', null],
    'el empaque es el producto' => ['SOBRE BLANCO OFICIO', 'SOBRE BLANCO OFICIO', null, null],
    'sin caja' => ['MICROPORE COLOR BLANCO 1" X 10 YD SIN CAJA [NEXCARE]', 'MICROPORE COLOR BLANCO 1" X 10 yd SIN CAJA', 'NEXCARE', null],
    'calibre de aguja' => ['JERINGA DESECHABLE 10 ML 21g x 1 1/2 x100 UNIDADES (CHANGZHOU)', 'JERINGA DESECHABLE 10 mL 21G X 1 1/2', 'CHANGZHOU', '100 unidades'],
    'sin nada que limpiar' => ['LORATADINA SUSPENSION 60 mL', 'LORATADINA SUSPENSION 60 mL', null, null],
]);

it('escribe cada medida de una sola forma', function (string $source, string $name) {
    expect((new NameCleaner)->clean($source)->name)->toBe($name);
})->with([
    'pegada' => ['ACETAMINOFEN 500MG', 'ACETAMINOFEN 500 mg'],
    'con punto' => ['ANDIMIBE 20/10 MG.', 'ANDIMIBE 20/10 mg'],
    'minúsculas' => ['TYLOFEN JARABE 120 ml', 'TYLOFEN JARABE 120 mL'],
    'razón' => ['AERO MED JARABE 15MG/5ML', 'AERO MED JARABE 15 mg/5 mL'],
    'razón sin número' => ['DEXASEL 8mg/ml AMPOLLA', 'DEXASEL 8 mg/mL AMPOLLA'],
    'gramos' => ['CERELAC INFANTIL 400 GR.', 'CERELAC INFANTIL 400 g'],
    'centímetros cúbicos' => ['JERINGA 5cc', 'JERINGA 5 mL'],
    'microgramos y UI' => ['BUDENA NASAL 100 MCG / VITAMINA D3 1000 U.I.', 'BUDENA NASAL 100 mcg / VITAMINA D3 1000 UI'],
    'onzas' => ['BIBERON 8 Oz / FRASCO 4onz', 'BIBERON 8 oz / FRASCO 4 oz'],
    'libras y litros' => ['BICARBONATO 1/2 libra / AGUA 1 LT', 'BICARBONATO 1/2 lb / AGUA 1 L'],
    'porcentaje' => ['ANDICAINA SPRAY 5 % 30 ML', 'ANDICAINA SPRAY 5% 30 mL'],
    'texto en mayúsculas' => ['GANOL 120mg/5ml JARABE sabor a fresa', 'GANOL 120 mg/5 mL JARABE SABOR A FRESA'],
    'x suelta al final' => ['LIZOVAG CREMA x', 'LIZOVAG CREMA'],
    'punto final' => ['PEPE FRUTERO AZUL BABY NIÑO.', 'PEPE FRUTERO AZUL BABY NIÑO'],
    'punto de abreviatura' => ['GLAUCUDEX 0.2 mL SOL. OFT.', 'GLAUCUDEX 0.2 mL SOL. OFT.'],
    'pegada a la palabra' => ['FLUIDEX SABOR NARANJA SUSPENSION120 ML', 'FLUIDEX SABOR NARANJA SUSPENSION 120 mL'],
    'variantes de gramos' => ['ISOMIL NG II 850GMS. / TERBIX 20GRMS.', 'ISOMIL NG II 850 g / TERBIX 20 g'],
    'no toca vitaminas ni códigos' => ['COMPLEJO B12 D3 Q10', 'COMPLEJO B12 D3 Q10'],
    'calibre con numeral' => ['AGUJA HIPODERMICA #23 GR X 1 1/4', 'AGUJA HIPODERMICA #23 GR X 1 1/4'],
]);

it('conserva el original si la limpieza no deja un nombre', function () {
    expect((new NameCleaner)->clean('X 100 TABLETAS [MK]')->name)->toBe('X 100 TABLETAS [MK]');
});
