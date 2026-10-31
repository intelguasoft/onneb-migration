# Importador de destino

El paquete no conoce sus modelos. El destino se conecta implementando un contrato:

```php
interface CatalogImporter
{
    public function import(MappedCatalog $catalog, bool $dryRun): ImportResult;
}
```

## Ejemplo

```php
namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RealsoftGt\OnneB\Contracts\CatalogImporter;
use RealsoftGt\OnneB\Data\ImportResult;
use RealsoftGt\OnneB\Data\MappedCatalog;

class ProductImporter implements CatalogImporter
{
    public function import(MappedCatalog $catalog, bool $dryRun): ImportResult
    {
        $existing = Product::query()->pluck('sku')->flip();

        $new = array_filter(
            $catalog->products,
            fn (array $product): bool => ! $existing->has($product['sku']),
        );

        if (! $dryRun) {
            DB::transaction(function () use ($new): void {
                foreach ($new as $product) {
                    Product::create([
                        'sku' => $product['sku'],
                        'name' => $product['name'],
                        'price' => $product['price'],
                    ]);
                }
            });
        }

        return new ImportResult(
            summary: ['Productos a crear' => count($new)],
            issues: $catalog->issues,
            pending: [
                ['Inventario', 'No se migran existencias.', 'Hacer la toma física.'],
            ],
        );
    }
}
```

## Reglas recomendadas

- **Respete `$dryRun`:** debe calcular lo mismo sin escribir nada.
- **Use una transacción:** una carga a medias es peor que ninguna.
- **Use sus modelos:** así se aplican eventos y reglas de dominio.
- **Haga la corrida repetible:** no pise lo corregido a mano; omita lo que ya exista.
- **Devuelva `$catalog->issues`:** puede agregar incidencias propias, como `barcode_en_uso`.
- **No migre existencias como dato definitivo:** los saldos de `INVSALDO` solo orientan la revisión.

## `ImportResult`

| Campo | Contenido |
|---|---|
| `summary` | Etiqueta => valor. Aparece en la consola y en la hoja **Resumen**. |
| `issues` | Incidencias que irán a la hoja **Por corregir**. Sus códigos deben existir en `IssueCatalog`. |
| `pending` | Tareas `[tema, situación, acción]` de la hoja **Pendientes**. Si está vacío, la hoja se omite. |

## Usarlo

Con el comando, registrando el contrato:

```php
$this->app->bind(CatalogImporter::class, ProductImporter::class);
```

```bash
php artisan onneb:migrate
php artisan onneb:migrate --execute
php artisan onneb:migrate --execute --force   # producción sin confirmar
```

Desde un seeder o un servicio, con importadores que requieran datos propios:

```php
use RealsoftGt\OnneB\OnneBMigrator;

public function run(OnneBMigrator $migrator): void
{
    $result = $migrator->run(new ProductImporter($company), dryRun: false);

    $this->command?->info("Revisión: {$result->reportPath}");
}
```

`run()` simula por defecto. Cada corrida, simulada o real, genera el Excel.
