# Reglas de mapeo

`CatalogMapper` traduce las filas de OnneB sin tocar ninguna base y devuelve un `MappedCatalog`.

## Producto mapeado

```php
[
    'sku' => '1005',
    'barcode' => '7410001010800',
    'name' => 'AMOXICILINA',
    'description' => 'AMOXICILINA X 100 CAPSULAS (MK)',
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

OnneB mezcla en la descripción el laboratorio y el empaque. En una factura, `TIRAS REACTIVAS X 100 UNIDADES` promete el empaque completo aunque se venda una fracción. Por eso el nombre conserva solo lo que identifica al producto, y el contenido pasa a la presentación:

| OnneB | Nombre | Presentaciones |
|---|---|---|
| `ACETAMINOFEN 500MG CAJA X 100 TABLETAS [MK]` | `ACETAMINOFEN 500 mg` | Tableta, Blíster x 10 tabletas, Caja x 100 tabletas |
| `LOSARTAN 100 MG X 30 TABLETAS [ECAR]` | `LOSARTAN 100 mg` | Caja x 30 tabletas |
| `REFORZADEC NIÑOS x25 AMPOLLAS DE 5ml (BIOVAL)` | `REFORZADEC NIÑOS 5 mL` | Ampolla, Caja x 25 ampollas |
| `GANOL 120mg/5ml JARABE sabor a fresa` | `GANOL 120 mg/5 mL JARABE SABOR A FRESA` | Frasco |

Reglas:

- Se quita el texto final entre paréntesis o corchetes, que en OnneB es el laboratorio, y la `x` que suele seguirlo.
- Se quita el contenido del empaque, como `X 30 TABLETAS`, `*30 TABLETAS`, `X 25 SOBRES X 4 CAPSULAS` o `X 30+15 TABLETAS`, junto con la palabra de empaque que lo antecede (`CAJA`, `DISPENSADOR`, `FRASCO`...).
- Se conservan la concentración, el tamaño (`120 mL`, que solo pierde la `X`), las medidas (`6 X 10 yd`), los paréntesis intermedios, como un principio activo, y la forma farmacéutica (`TABLETAS MASTICABLES`).
- El nombre original queda en `description`, salvo que `DESPROD2` o `DESPROD3` traigan otro texto.

### Escritura uniforme

Cada nombre se escribe de una sola forma, para que dos productos iguales no se vean distintos:

- El texto va en mayúsculas.
- Cada medida va separada del número, en su notación y sin punto final:

| En OnneB | Queda |
|---|---|
| `MG`, `MG.`, `mg` | `mg` |
| `MCG`, `UG` | `mcg` |
| `G`, `GR`, `GR.`, `GRS`, `GRM`, `GMS`, `GRAMOS` | `g` |
| `KG`, `KGS` | `kg` |
| `ML`, `ML.`, `ml`, `CC` | `mL` |
| `L`, `LT`, `LTS`, `LITROS` | `L` |
| `UI`, `U.I.` | `UI` |
| `OZ`, `Oz`, `ONZ`, `ONZAS` | `oz` |
| `LB`, `LIBRA`, `LIBRAS` | `lb` |
| `CM`, `MM`, `MTS`, `YDS`, `YARDAS` | `cm`, `mm`, `m`, `yd` |

- Las razones quedan como `250 mg/5 mL`, y el porcentaje va pegado: `5%`.
- Una medida pegada a la palabra anterior se separa: `SUSPENSION120 ML` queda `SUSPENSION 120 mL`.
- Se quitan la `x` suelta al final y el punto tras una palabra completa. El punto de una abreviatura, como `SOL. OFT.` o `I.M.`, se conserva.
- En agujas, jeringas, catéteres, lancetas y suturas, la `G` es el calibre y no se toca: `21G`, `#23 GR`.

### Presentaciones

El contenido se pasa a las presentaciones así:

| Caso | Resultado | Incidencia |
|---|---|---|
| Una presentación trae justo ese contenido | La base toma el nombre de la pieza y cada paquete suma el sustantivo: `Tableta`, `Caja x 100 tabletas` | Ninguna |
| El producto tiene una sola presentación, que no es Unidad | Esa presentación lo declara: `Caja x 30 tabletas` | Ninguna |
| Ninguna presentación coincide | No se inventa el dato | `contenido_por_confirmar` |

El empaque no se repite: queda `Caja x 6 ampollas`, nunca `Ampolla x 6 ampollas` ni `Caja x 4 cajas`.

Si el laboratorio del nombre no se parece a la marca de OnneB, se conserva la marca y se reporta `marca_distinta`: sin el laboratorio en el nombre, la marca es lo único que lo muestra al vender. Se toleran abreviaturas y errores de digitación, como `KIMBERLY CLARCK` frente a `KIMBERLY CLARK`.

La hoja **Nombres** del Excel lista cada cambio. Para conservar los nombres de OnneB tal como están:

```php
'mapping' => [
    'clean_names' => false, // o ONNEB_CLEAN_NAMES=false
],
```

Sin limpieza no se aplica la escritura uniforme: solo se quitan los espacios repetidos y la `x` tras el laboratorio. Las marcas de `onneb.mapping.generic_brands` no se asignan; por defecto, `VARIOS`.

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
