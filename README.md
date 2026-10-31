# OnneB Migration para Laravel

[![Tests](https://github.com/intelguasoft/onneb-migration/actions/workflows/tests.yml/badge.svg)](https://github.com/intelguasoft/onneb-migration/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/realsoftgt/onneb-migration.svg)](https://packagist.org/packages/realsoftgt/onneb-migration)
[![Licencia](https://img.shields.io/packagist/l/realsoftgt/onneb-migration.svg)](LICENSE.md)

Migra el catálogo de **OnneB** (SQL Server) a una aplicación Laravel: productos, presentaciones, precios, marcas e impuestos, con un Excel que explica cada decisión dudosa para que una persona la corrija.

- **Dos orígenes de datos con el mismo resultado:** conexión directa a SQL Server o archivos JSON sin ninguna conexión externa.
- **Extracción verificable:** cada archivo se valida contra un manifiesto con filas, columnas y SHA-256.
- **Solo lectura sobre OnneB:** el paquete únicamente ejecuta `SELECT`, siempre filtrado por empresa.
- **Mapeo de presentaciones:** resuelve la unidad base, factores, costos, códigos de barras y nombres.
- **Nada se descarta en silencio:** cada decisión queda como incidencia con prioridad y acción.
- **Destino libre:** usted conecta sus propios modelos con un contrato de un método.
- **Simulación por defecto:** nada se escribe hasta pedirlo de forma explícita.

## Requisitos

| Requisito | Versión |
|---|---|
| PHP | 8.3 o superior |
| Laravel | 12 o 13 |
| Extensiones | `mbstring`, `zip`, `gd` |
| `pdo_sqlsrv` | Solo para leer de SQL Server o generar extracciones |

## Instalación

```bash
composer require realsoftgt/onneb-migration
php artisan vendor:publish --tag=onneb-config
```

## Inicio rápido

### Opción A: desde archivos, sin conectarse a SQL Server

En un equipo con acceso a OnneB:

```env
ONNEB_EMPNIT=MIEMPRESA
ONNEB_DB_HOST=192.0.2.10
ONNEB_DB_DATABASE=ONNE
ONNEB_DB_USERNAME=lectura
ONNEB_DB_PASSWORD=
```

```bash
php artisan onneb:export
```

Copie la carpeta `database/data/onneb` a la aplicación de destino y ejecute:

```bash
php artisan onneb:verify
php artisan onneb:review
```

### Opción B: directo desde SQL Server

```env
ONNEB_SOURCE=database
```

```bash
php artisan onneb:review
```

En ambos casos obtiene un Excel en `storage/app/private/onneb`. Para importar a sus modelos, implemente `CatalogImporter`:

```php
use RealsoftGt\OnneB\Contracts\CatalogImporter;
use RealsoftGt\OnneB\Data\ImportResult;
use RealsoftGt\OnneB\Data\MappedCatalog;

class ProductImporter implements CatalogImporter
{
    public function import(MappedCatalog $catalog, bool $dryRun): ImportResult
    {
        if (! $dryRun) {
            // Crear sus productos con $catalog->products dentro de una transacción.
        }

        return new ImportResult(['Productos' => count($catalog->products)], $catalog->issues);
    }
}
```

Regístrelo en un service provider y ejecute la migración:

```php
$this->app->bind(CatalogImporter::class, ProductImporter::class);
```

```bash
php artisan onneb:migrate            # simula
php artisan onneb:migrate --execute  # escribe
```

## Comandos

| Comando | Qué hace |
|---|---|
| `onneb:export {path?} {--empnit=}` | Extrae SQL Server a JSON verificable |
| `onneb:verify {path?}` | Valida una extracción sin usarla |
| `onneb:review {--source=}` | Genera el Excel sin escribir en ningún destino |
| `onneb:migrate {--source=} {--execute} {--force}` | Importa al `CatalogImporter` registrado |

## Documentación

- [Orígenes de datos](docs/sources.md)
- [Formato de la extracción](docs/snapshot-format.md)
- [Reglas de mapeo](docs/mapping.md)
- [Importador de destino](docs/importers.md)
- [Excel de revisión](docs/review-report.md)
- [Seguridad y datos](docs/security.md)

## Seguridad de los datos

Una extracción contiene **costos, precios y saldos de su empresa**. Nunca la incluya en un repositorio público. Lea [Seguridad y datos](docs/security.md) antes de usar el paquete en producción.

## Desarrollo

```bash
composer test
composer analyse
composer format
```

Consulte [CONTRIBUTING.md](CONTRIBUTING.md) y [CHANGELOG.md](CHANGELOG.md). Para reportar una vulnerabilidad, siga [SECURITY.md](SECURITY.md).

## Aviso

Paquete independiente, sin relación con los creadores de OnneB. OnneB es propiedad de sus respectivos titulares.

## Licencia

MIT. Consulte [LICENSE.md](LICENSE.md).
