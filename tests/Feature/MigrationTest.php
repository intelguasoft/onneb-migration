<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RealsoftGt\OnneB\Contracts\CatalogImporter;
use RealsoftGt\OnneB\Data\ImportResult;
use RealsoftGt\OnneB\Data\MappedCatalog;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\Mapping\CatalogMapper;
use RealsoftGt\OnneB\OnneBMigrator;
use RealsoftGt\OnneB\Review\IssueCatalog;
use RealsoftGt\OnneB\Snapshot\SnapshotWriter;
use RealsoftGt\OnneB\Support\NullImporter;

class RecordingImporter implements CatalogImporter
{
    public ?bool $dryRun = null;

    public int $imported = 0;

    public function import(MappedCatalog $catalog, bool $dryRun): ImportResult
    {
        $this->dryRun = $dryRun;
        $this->imported = count($catalog->products);

        return new ImportResult(['Productos creados' => $dryRun ? 0 : $this->imported], $catalog->issues, [
            ['Inventario', 'No se migran existencias.', 'Hacer la toma física.'],
        ]);
    }
}

/**
 * @return array<string, list<list<mixed>>>
 */
function workbook(string $path): array
{
    $book = IOFactory::load($path);

    return collect($book->getSheetNames())
        ->mapWithKeys(fn (string $name): array => [$name => $book->getSheetByName($name)->toArray(null, false, false)])
        ->all();
}

beforeEach(function () {
    Storage::fake('local');
    File::deleteDirectory($this->snapshotPath());
    $this->createOnneBTables();
    app(SnapshotWriter::class)->write($this->snapshotPath());
});

afterEach(function () {
    File::deleteDirectory($this->snapshotPath());
});

it('simula por defecto y entrega al importador el catálogo mapeado', function () {
    $importer = new RecordingImporter;

    $result = app(OnneBMigrator::class)->run($importer);

    expect($importer->dryRun)->toBeTrue()
        ->and($importer->imported)->toBe(2)
        ->and($result->dryRun)->toBeTrue()
        ->and($result->reportPath)->toContain('simulacion')
        ->and($result->mapped->products[0]['name'])->toBe('AMOXICILINA');
});

it('genera el Excel con el contexto para corregir', function (string $source) {
    config(['onneb.review.target_name' => 'Mi ERP']);

    $result = app(OnneBMigrator::class)->run(new RecordingImporter, dryRun: false, source: $source);
    $book = workbook($result->reportPath);
    $noPrice = collect($book['Por corregir'])->firstWhere(1, '1006');

    expect(array_keys($book))->toBe(['Resumen', 'Por corregir', 'Presentaciones', 'Nombres', 'Mayoreo', 'Pendientes'])
        ->and($book['Nombres'][0])->toContain('Nombre en Mi ERP')
        ->and(collect($book['Nombres'])->firstWhere(0, '1005'))->toBe(['1005', 'AMOXICILINA X 100 (MK)x', 'AMOXICILINA', 'MK', 'Unidad, Caja x 100'])
        ->and($book['Resumen'][1][0])->toContain('Catálogo cargado')
        ->and($book['Resumen'][2][0])->toContain('EMPRESA', "origen {$source}")
        ->and($book['Resumen'][4])->toContain('Productos creados')
        ->and($book['Por corregir'][0])->toContain('Presentaciones en Mi ERP')
        ->and($noPrice[0])->toBe('Alta')
        ->and($noPrice[4])->toBe('Sin precio')
        ->and($book['Mayoreo'][1][0])->toBe('1005')
        ->and($book['Mayoreo'][1][5])->toEqual(170)
        ->and($book['Pendientes'][1][0])->toBe('Inventario');
})->with(['snapshot', 'database']);

it('separa en otro libro solo lo que requiere a una persona', function () {
    DB::connection('fixture')->table('PRECIOS')->where('ID', 1)->update(['COSTO' => '0.0100']);
    app(SnapshotWriter::class)->write($this->snapshotPath());

    $result = app(OnneBMigrator::class)->run(new RecordingImporter);
    $rows = workbook($result->attentionPath)['Atención'];
    $codes = array_column(array_slice($rows, 1), 1);

    expect($result->attentionPath)->toEndWith('-simulacion.xlsx')->toContain('atencion-catalogo-')
        ->and($rows[0])->toBe(['Prioridad', 'Código', 'Producto', 'Marca', 'Existencia OnneB', 'Qué revisar', 'Detalle', 'Qué hacer', 'Presentaciones en Destino', 'Nombre en OnneB', 'Corregido', 'Responsable', 'Notas'])
        ->and($codes)->toBe(array_values(array_unique($codes)))
        ->and(collect($rows)->firstWhere(1, '1006')[5])->toBe('Sin precio')
        ->and(collect($rows)->pluck(5)->implode(' '))->not->toContain('Costo derivado');
});

it('omite la hoja de pendientes si el importador no reporta ninguno', function () {
    $result = app(OnneBMigrator::class)->run(new NullImporter);

    expect(array_keys(workbook($result->reportPath)))->not->toContain('Pendientes');
});

it('escribe los valores como texto para neutralizar fórmulas', function () {
    DB::connection('fixture')->table('PRODUCTOS')->update(['CODPROD2' => '=1+1']);
    app(SnapshotWriter::class)->write($this->snapshotPath());

    $result = app(OnneBMigrator::class)->run(new NullImporter);
    $sheet = IOFactory::load($result->reportPath)->getSheetByName('Por corregir');

    expect($sheet->getCell('B2')->getDataType())->toBe('s')
        ->and(collect($sheet->toArray(null, false, false))->flatten()->contains(fn ($value) => is_string($value) && str_contains($value, '=1+1')))->toBeTrue();
});

it('admite medidas propias y detiene las desconocidas', function () {
    DB::connection('fixture')->table('PRECIOS')->where('ID', 2)->update(['CODMEDIDA' => 'BARRIL']);
    app(SnapshotWriter::class)->write($this->snapshotPath());

    expect(fn () => app(OnneBMigrator::class)->run(new NullImporter))->toThrow(OnneBException::class, 'BARRIL');

    config(['onneb.mapping.extra_units' => ['BARRIL' => ['BAR', 'Barril']]]);

    $names = array_column(app(OnneBMigrator::class)->run(new NullImporter)->mapped->products[0]['presentations'], 'name');

    expect($names)->toContain('Barril x 100');
});

it('tiene una ficha de revisión para cada incidencia del mapeo', function () {
    expect(array_keys(IssueCatalog::ISSUES))->toEqualCanonicalizing(array_keys(CatalogMapper::ISSUES));
});

it('nunca reduce un límite de memoria mayor', function () {
    $current = ini_get('memory_limit');
    ini_set('memory_limit', '2G');
    config(['onneb.memory_limit' => '1G']);

    app(OnneBMigrator::class)->run(new NullImporter);

    expect(ini_get('memory_limit'))->toBe('2G');

    ini_set('memory_limit', $current);
});
