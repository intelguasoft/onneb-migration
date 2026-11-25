# Changelog

Todos los cambios relevantes se documentan aquí, con el formato de [Keep a Changelog](https://keepachangelog.com/es/1.1.0/) y [Versionado Semántico](https://semver.org/lang/es/).

## [Sin publicar]

### Agregado

- Formato del SKU con `onneb.mapping.sku_prefix` y `onneb.mapping.sku_pad`: con `SKU-` y 5, el `CODPROD` 123 queda `SKU-00123`. Sin configurar, el SKU sigue siendo el `CODPROD`.
- Cada producto mapeado lleva su `CODPROD` original en `source_code`.

### Cambiado

- El Excel de revisión relaciona cada producto con OnneB por `source_code`, no por el SKU.

## [1.2.0] - 2026-11-18

### Agregado

- Cada presentación mapeada lleva sus precios de mayoreo de OnneB en `wholesale` (`A`, `B` y `C`), para armar listas de precios en el destino.
- Incidencias `mayoreo_bajo_costo` y `mayoreo_mayor_precio`, solo para los mayoreos de `onneb.mapping.wholesale_tiers`.

## [1.1.0] - 2026-11-05

### Agregado

- Limpieza de nombres: se quitan el laboratorio final y el contenido del empaque, como `CAJA X 100 TABLETAS`, que pasa al nombre de la presentación. Se desactiva con `onneb.mapping.clean_names`.
- Incidencias `contenido_por_confirmar` y `marca_distinta`.
- Hoja **Nombres** en el Excel de revisión, con cada nombre antes y después.
- Libro de atención (`atencion-catalogo-*.xlsx`) junto al de revisión: una fila por producto que requiere a una persona, por prioridad y existencia, con columnas para responsable y notas. Su ruta está en `MigrationResult::$attentionPath`.
- Escritura uniforme de nombres: texto en mayúsculas y medidas en su notación, separadas del número y sin punto (`500 mg`, `250 mg/5 mL`, `30 g`). Los calibres de agujas y catéteres no se tocan.

### Cambiado

- Por defecto, el nombre del producto ya no incluye el laboratorio ni el contenido del empaque; el original queda en `description`.
- La presentación base toma el nombre de la pieza cuando se conoce (`Tableta` en lugar de `Unidad`) y ninguna presentación repite su empaque (`Ampolla x 6 ampollas`).

## [1.0.1] - 2026-11-03

### Corregido

- `onneb:export` y `onneb:verify` respetan `onneb.memory_limit`: con el límite de 128M que trae PHP no alcanzaban a procesar un catálogo completo.

## [1.0.0] - 2026-10-31

### Agregado

- Orígenes `database` (SQL Server) y `snapshot` (archivos JSON) detrás de `CatalogSource`.
- Extracción verificable con manifiesto de filas, columnas y SHA-256.
- `CatalogMapper` para productos, servicios, presentaciones, costos, códigos de barras y marcas.
- Contrato `CatalogImporter` y orquestador `OnneBMigrator`, con simulación por defecto.
- Excel de revisión con resumen, incidencias, presentaciones, mayoreo y pendientes.
- Comandos `onneb:export`, `onneb:verify`, `onneb:review` y `onneb:migrate`.
- Soporte para Laravel 12 y 13 con PHP 8.3 o superior.

[Sin publicar]: https://github.com/intelguasoft/onneb-migration/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/intelguasoft/onneb-migration/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/intelguasoft/onneb-migration/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/intelguasoft/onneb-migration/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/intelguasoft/onneb-migration/releases/tag/v1.0.0
