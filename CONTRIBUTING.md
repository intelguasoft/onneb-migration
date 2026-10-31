# Cómo contribuir

## Preparación

```bash
git clone https://github.com/intelguasoft/onneb-migration.git
cd onneb-migration
composer install
```

## Antes de abrir un pull request

```bash
composer test      # Pest
composer analyse   # PHPStan nivel 6
composer format    # Pint
```

- Agregue pruebas para todo cambio de comportamiento.
- Toda regla nueva de mapeo debe generar una incidencia y tener su ficha en `IssueCatalog`.
- Actualice `CHANGELOG.md` y la carpeta `docs/`.
- Un cambio en el formato de la extracción requiere subir `SnapshotWriter::FORMAT` y mantener la lectura de formatos anteriores.

## Datos de prueba

**Nunca use datos reales** en pruebas, ejemplos, issues o pull requests. Esto incluye costos, precios, NIT, nombres de clientes y extracciones de OnneB.

## Versiones

El proyecto sigue versionado semántico. Las clases públicas son los contratos, `src/Data`, `OnneBMigrator`, `SourceManager` y el formato de la extracción.
