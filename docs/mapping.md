# Reglas de mapeo

`CatalogMapper` traduce las filas de OnneB sin tocar ninguna base y devuelve un `MappedCatalog`.

## Producto mapeado

```php
[
    'sku' => '1005',
    'barcode' => '7410001010800',
    'name' => 'AMOXICILINA X 100 CAPSULAS (MK)',
    'description' => null,
    'brand' => 'MK',
    'is_exempt' => false,
    'type' => 'good',            // good | service
    'base_unit' => 'UNIDAD',
    'standard_cost' => 0.5,
    'price' => 2.0,
    'min_stock' => null,
    'max_stock' => null,
    'presentations' => [
        [
            'unit' => 'UNIDAD',
            'name' => 'Unidad',
            'base_factor' => 1.0,
            'price' => null,     // La base usa el precio del producto.
            'is_base' => true,
            'is_purchasable' => true,
            'is_sellable' => true,
            'is_active' => true,
        ],
    ],
]
```

Los servicios (`TIPOPROD = S`) no llevan presentaciones y conservan una sola tarifa.

## Presentación base

`PRECIOS.EQUIVALE` indica cuántas unidades base contiene cada presentación.

| Caso en OnneB | Resultado | Incidencia |
|---|---|---|
| Una fila con factor 1 | Es la base; las demás conservan su factor | Ninguna |
| Varias filas con factor 1 | La de menor precio es la base; las demás quedan inactivas | `base_ambigua` |
| Caja con factor 1 sobre Unidad, y `UXC` coherente con los precios | La caja toma el factor de `UXC` | `factor_por_uxc` |
| Ninguna con factor 1 | Se crea una Unidad no vendible como base | `base_implicita` |
| Unidad con factor mayor que 1 | Unidad queda de base; las demás, inactivas | `factor_no_confiable` |
| Sin filas en `PRECIOS` | Solo la base en Unidad, sin precio | `sin_presentaciones` |

## Precios

- Un precio en 0 se convierte en `null` e incidencia `sin_precio`.
- Un paquete que vale menos que la base queda inactivo: `precio_invertido`.
- Un paquete 1.5 veces más caro por unidad que la base sigue activo: `precio_incoherente`.
- Las columnas `MAYOREOA`, `MAYOREOB` y `MAYOREOC` no se mapean; aparecen en la hoja **Mayoreo**.

## Costos

Un costo de 0.10 o menos se considera relleno. El orden de búsqueda es:

1. La presentación base.
2. Las presentaciones activas, de mayor a menor factor, divididas entre su factor.
3. `PRODUCTOS.COSTO`.

Los casos 2 y 3 generan `costo_derivado`. Sin ningún costo real queda en 0 con `sin_costo`.

## Códigos de barras

`CODPROD2` se usa como código de barras salvo que:

- Sea igual al SKU o esté vacío.
- Otro producto declare el mismo código o lo use como SKU: `barcode_compartido`.

Un código único que no es GTIN válido se conserva con `barcode_no_gtin`.

## Nombres y marcas

- Se eliminan espacios repetidos y saltos de línea.
- Se quita la `x` que OnneB deja tras el laboratorio: `(HERSIL)x`. El original queda en `description`.
- Las marcas de `onneb.mapping.generic_brands` no se asignan. Por defecto: `VARIOS`.

## Medidas

El paquete incluye 52 medidas en `CatalogMapper::DEFAULT_UNITS`. Una medida desconocida **detiene** la migración, para no inventar una unidad. Agréguela en la configuración:

```php
'mapping' => [
    'extra_units' => [
        'BARRIL' => ['BAR', 'Barril'],
    ],
],
```

## Incidencias

Consulte el catálogo completo en `RealsoftGt\OnneB\Review\IssueCatalog` y en [Excel de revisión](review-report.md).
