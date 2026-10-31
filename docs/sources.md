# Orígenes de datos

Todo origen implementa `CatalogSource` y entrega las mismas tablas. `Catalog::fromTables()` es el único punto que las interpreta, así que el mapeo y el Excel no dependen de dónde se leyeron.

| | `snapshot` | `database` |
|---|---|---|
| Lee de | Archivos JSON | SQL Server |
| Requiere `pdo_sqlsrv` | No | Sí |
| Requiere red hacia OnneB | No | Sí |
| Datos | Fecha de la extracción | Al momento de leer |
| Valida integridad | Sí, contra el manifiesto | No aplica |
| Uso típico | Producción, CI, ambientes aislados | Revisiones frecuentes con OnneB activo |

Elija el origen por defecto con `ONNEB_SOURCE`, o por corrida:

```bash
php artisan onneb:review --source=database
```

```php
$migrator->run($importer, dryRun: true, source: 'snapshot');
```

## Archivos (`snapshot`)

Es el origen por defecto. Lee de `database/data/onneb`, o de la carpeta indicada en `ONNEB_SNAPSHOT_PATH`.

Genere la extracción donde haya acceso a OnneB:

```bash
php artisan onneb:export
php artisan onneb:export /ruta/destino --empnit=MIEMPRESA
```

El comando lee todo antes de escribir. Si OnneB falla o no devuelve productos, no reemplaza la extracción anterior.

Antes de cada lectura se valida que existan los archivos y que coincidan filas, columnas y SHA-256. También puede validarla por separado:

```bash
php artisan onneb:verify
```

## SQL Server (`database`)

Con credenciales propias:

```env
ONNEB_SOURCE=database
ONNEB_EMPNIT=MIEMPRESA
ONNEB_DB_HOST=192.0.2.10
ONNEB_DB_PORT=1433
ONNEB_DB_DATABASE=ONNE
ONNEB_DB_USERNAME=lectura
ONNEB_DB_PASSWORD=
```

La conexión se registra solo al leer, sin agregarla a `config/database.php`.

Si su aplicación ya define la conexión, use su nombre:

```env
ONNEB_DB_CONNECTION=legacy
```

`ONNEB_EMPNIT` es obligatorio: OnneB comparte tablas entre varias empresas y el paquete nunca lee sin ese filtro.

## Origen propio

Registre cualquier otra fuente, como una API, en un service provider:

```php
use RealsoftGt\OnneB\Sources\SourceManager;

$this->app->afterResolving(SourceManager::class, function (SourceManager $sources): void {
    $sources->extend('api', fn () => new ApiSource);
});
```

Su clase debe implementar `CatalogSource` y puede construir el resultado con `Catalog::fromTables()`.
