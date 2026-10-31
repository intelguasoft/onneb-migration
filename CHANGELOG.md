# Changelog

Todos los cambios relevantes se documentan aquí, con el formato de [Keep a Changelog](https://keepachangelog.com/es/1.1.0/) y [Versionado Semántico](https://semver.org/lang/es/).

## [Sin publicar]

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

[Sin publicar]: https://github.com/intelguasoft/onneb-migration/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/intelguasoft/onneb-migration/releases/tag/v1.0.0
