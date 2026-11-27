<?php

namespace RealsoftGt\OnneB\Review;

/**
 * Traduce cada incidencia del mapeo a lo que necesita quien la corrige:
 * qué significa, qué decidió la migración y qué falta hacer.
 */
class IssueCatalog
{
    public const HIGH = 'Alta';

    public const MEDIUM = 'Media';

    public const LOW = 'Baja';

    public const PRIORITIES = [self::HIGH => 1, self::MEDIUM => 2, self::LOW => 3];

    /** @var array<string, array{label: string, priority: string, meaning: string, done: string, action: string}> */
    public const ISSUES = [
        'mayoreo_bajo_costo' => [
            'label' => 'Mayoreo bajo el costo',
            'priority' => self::HIGH,
            'meaning' => 'Un precio de mayoreo de OnneB es menor que el costo de esa presentación: se vendería con pérdida.',
            'done' => 'Se cargó tal cual en su lista de precios.',
            'action' => 'Revisar el costo y el precio de mayoreo antes de venderle a ese tipo de cliente.',
        ],
        'mayoreo_mayor_precio' => [
            'label' => 'Mayoreo más caro que el público',
            'priority' => self::MEDIUM,
            'meaning' => 'Un precio de mayoreo de OnneB es mayor que el precio al público.',
            'done' => 'Se cargó tal cual en su lista de precios.',
            'action' => 'Confirmar el precio de mayoreo o corregirlo en la lista.',
        ],
        'sin_precio' => [
            'label' => 'Sin precio',
            'priority' => self::HIGH,
            'meaning' => 'OnneB tiene el precio en 0.',
            'done' => 'Se creó sin precio.',
            'action' => 'Definir el precio en Productos antes de vender.',
        ],
        'sin_presentaciones' => [
            'label' => 'Sin presentaciones',
            'priority' => self::HIGH,
            'meaning' => 'El producto no tiene filas en PRECIOS.',
            'done' => 'Se creó solo la base en Unidad, sin precio.',
            'action' => 'Confirmar la unidad, crear las presentaciones y definir el precio.',
        ],
        'sin_costo' => [
            'label' => 'Sin costo',
            'priority' => self::HIGH,
            'meaning' => 'OnneB solo tiene costos de relleno (Q0.10 o menos).',
            'done' => 'Costo estándar en 0.',
            'action' => 'Definir el costo con la última factura de compra antes de la toma física.',
        ],
        'costo_mayor_precio' => [
            'label' => 'Costo mayor que el precio',
            'priority' => self::HIGH,
            'meaning' => 'Se vendería con pérdida, o el costo o el precio están mal en OnneB.',
            'done' => 'Se conservaron ambos valores.',
            'action' => 'Revisar la última compra y corregir el costo o el precio.',
        ],
        'base_ambigua' => [
            'label' => 'Base ambigua',
            'priority' => self::HIGH,
            'meaning' => 'Varias presentaciones declaran factor 1, así que OnneB no dice cuántas unidades trae cada una.',
            'done' => 'La más barata quedó de base; las demás, inactivas.',
            'action' => 'Definir el factor real de cada presentación y activarla.',
        ],
        'factor_no_confiable' => [
            'label' => 'Factor no confiable',
            'priority' => self::HIGH,
            'meaning' => 'La Unidad declara un factor mayor que 1.',
            'done' => 'Unidad quedó de base con factor 1; las demás, inactivas.',
            'action' => 'Corregir los factores y activar las presentaciones.',
        ],
        'precio_invertido' => [
            'label' => 'Precio invertido',
            'priority' => self::HIGH,
            'meaning' => 'Un paquete vale menos que la presentación base.',
            'done' => 'El paquete quedó inactivo.',
            'action' => 'Corregir el precio o el factor y activarlo.',
        ],
        'base_implicita' => [
            'label' => 'Base implícita',
            'priority' => self::MEDIUM,
            'meaning' => 'Ninguna presentación vale 1 unidad, y el destino exige una base.',
            'done' => 'Se creó una Unidad que no se vende ni se compra.',
            'action' => 'Confirmar que el producto se vende por paquete. Contar en unidades en la toma física.',
        ],
        'factor_por_uxc' => [
            'label' => 'Factor tomado de UXC',
            'priority' => self::MEDIUM,
            'meaning' => 'La Caja declara factor 1, pero UXC y los precios indican otro.',
            'done' => 'La Caja tomó el factor de UXC y quedó activa.',
            'action' => 'Confirmar cuántas unidades trae la caja.',
        ],
        'precio_incoherente' => [
            'label' => 'Precio incoherente',
            'priority' => self::MEDIUM,
            'meaning' => 'El paquete sale más caro por unidad que la base.',
            'done' => 'Se conservó el precio y sigue activo.',
            'action' => 'Revisar el precio o el factor del paquete.',
        ],
        'barcode_compartido' => [
            'label' => 'Código de barras compartido',
            'priority' => self::MEDIUM,
            'meaning' => 'Varios productos declaran el mismo código y el POS tomaría el primero.',
            'done' => 'No se asignó a ninguno de ellos.',
            'action' => 'Escanear el producto físico y asignar el código correcto.',
        ],
        'barcode_en_uso' => [
            'label' => 'Código de barras en uso',
            'priority' => self::MEDIUM,
            'meaning' => 'El código ya pertenece a otro producto del destino.',
            'done' => 'No se asignó.',
            'action' => 'Definir cuál producto debe conservarlo.',
        ],
        'servicio_con_varias_tarifas' => [
            'label' => 'Servicio con varias tarifas',
            'priority' => self::MEDIUM,
            'meaning' => 'Un servicio no maneja presentaciones.',
            'done' => 'Se conservó una sola tarifa.',
            'action' => 'Confirmar la tarifa o crear un servicio por cada una.',
        ],
        'sku_duplicado' => [
            'label' => 'Código duplicado',
            'priority' => self::MEDIUM,
            'meaning' => 'El código se repite en OnneB al normalizarlo.',
            'done' => 'Se conservó el primero.',
            'action' => 'Confirmar si es el mismo producto; si no, crearlo con otro código.',
        ],
        'contenido_por_confirmar' => [
            'label' => 'Contenido por confirmar',
            'priority' => self::MEDIUM,
            'meaning' => 'El nombre declaraba un contenido, como «X 100 TABLETAS», que ninguna presentación trae.',
            'done' => 'Se quitó del nombre para no prometer en la factura lo que no se entrega. El original quedó en la descripción.',
            'action' => 'Revisar el empaque físico y corregir el factor o el nombre de cada presentación.',
        ],
        'marca_distinta' => [
            'label' => 'Laboratorio distinto de la marca',
            'priority' => self::MEDIUM,
            'meaning' => 'El laboratorio escrito en el nombre no coincide con la marca de OnneB, que es la que se verá al vender.',
            'done' => 'Se quitó del nombre y se conservó la marca de OnneB. El nombre original quedó en la descripción.',
            'action' => 'Confirmar el laboratorio con el empaque y corregir la marca del producto.',
        ],
        'costo_derivado' => [
            'label' => 'Costo derivado',
            'priority' => self::LOW,
            'meaning' => 'La presentación base trae un costo de relleno.',
            'done' => 'El costo se calculó desde el paquete o desde PRODUCTOS.COSTO.',
            'action' => 'Validar en la próxima compra.',
        ],
        'barcode_no_gtin' => [
            'label' => 'Código de barras interno',
            'priority' => self::LOW,
            'meaning' => 'El código no es un GTIN válido, pero es único.',
            'done' => 'Se conservó.',
            'action' => 'Sustituirlo si el empaque trae un código de fábrica.',
        ],
        'stock_maximo_invalido' => [
            'label' => 'Stock máximo inválido',
            'priority' => self::LOW,
            'meaning' => 'El máximo es menor que el mínimo.',
            'done' => 'Se conservó el mínimo y se omitió el máximo.',
            'action' => 'Definir el máximo al ajustar los puntos de reorden.',
        ],
        'proveedor_inexistente' => [
            'label' => 'Proveedor principal inexistente',
            'priority' => self::LOW,
            'meaning' => 'El producto apunta a un código que no está en PROVEEDORES.',
            'done' => 'Quedó sin proveedor principal; conserva los proveedores de sus compras.',
            'action' => 'Asignar el proveedor principal en el producto.',
        ],
    ];

    /** @var array<string, array{label: string, priority: string, meaning: string, done: string, action: string}> */
    public const SUPPLIER_ISSUES = [
        'nit_repetido' => [
            'label' => 'NIT repetido',
            'priority' => self::HIGH,
            'meaning' => 'Varios proveedores de OnneB tienen el mismo NIT: puede ser el mismo tercero dado de alta dos veces.',
            'done' => 'Se creó cada uno por separado, con su código de OnneB.',
            'action' => 'Confirmar si es el mismo proveedor; si lo es, pasar sus productos a uno y desactivar el otro.',
        ],
        'nit_invalido' => [
            'label' => 'NIT inválido',
            'priority' => self::HIGH,
            'meaning' => 'El NIT no cumple el dígito verificador de Guatemala: está mal escrito o es de otro país.',
            'done' => 'Se cargó tal cual.',
            'action' => 'Corregirlo con la última factura del proveedor antes de registrar compras.',
        ],
        'proveedor_sin_nit' => [
            'label' => 'Sin NIT',
            'priority' => self::MEDIUM,
            'meaning' => 'OnneB no tiene el NIT del proveedor.',
            'done' => 'Quedó con NIT CF.',
            'action' => 'Registrar el NIT con la última factura: sin él, la compra no da crédito fiscal.',
        ],
        'proveedor_con_saldo' => [
            'label' => 'Con saldo en OnneB',
            'priority' => self::MEDIUM,
            'meaning' => 'OnneB registra un saldo con el proveedor; negativo es lo que se le debe.',
            'done' => 'No se cargó como cuenta por pagar.',
            'action' => 'Cuadrar el saldo con contabilidad y registrarlo en la apertura de cuentas por pagar.',
        ],
        'credito_por_confirmar' => [
            'label' => 'Crédito por confirmar',
            'priority' => self::LOW,
            'meaning' => 'OnneB no dice los días de crédito ni si es de contado.',
            'done' => 'Quedó con 0 días de crédito.',
            'action' => 'Confirmar la condición de pago con el proveedor.',
        ],
    ];
}
