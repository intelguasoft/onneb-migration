# Changelog

Todos los cambios relevantes se documentan aquí, con el formato de [Keep a Changelog](https://keepachangelog.com/es/1.1.0/) y [Versionado Semántico](https://semver.org/lang/es/).

## [Sin publicar]

## [1.0.0]

### Agregado

- Orígenes `database` (SQL Server) y `snapshot` (archivos JSON) detrás de `CatalogSource`.
- Extracción verificable con manifiesto de filas, columnas y SHA-256.
- `CatalogMapper` para productos, servicios, presentaciones, costos, códigos de barras y marcas.
- Contrato `CatalogImporter` y orquestador `OnneBMigrator`, con simulación por defecto.
- Excel de revisión con resumen, incidencias, presentaciones, mayoreo y pendientes.
- Comandos `onneb:export`, `onneb:verify`, `onneb:review` y `onneb:migrate`.
- Soporte para Laravel 12 y 13 con PHP 8.3 o superior.
