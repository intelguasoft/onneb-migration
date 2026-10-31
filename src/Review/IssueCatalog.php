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
    ];
}
