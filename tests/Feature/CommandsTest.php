<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RealsoftGt\OnneB\Contracts\CatalogImporter;
use RealsoftGt\OnneB\Data\ImportResult;
use RealsoftGt\OnneB\Data\MappedCatalog;

beforeEach(function () {
    Storage::fake('local');
    File::deleteDirectory($this->snapshotPath());
    $this->createOnneBTables();
});

afterEach(function () {
    File::deleteDirectory($this->snapshotPath());
});

it('exporta y verifica una extracción', function () {
    $this->artisan('onneb:export')->assertSuccessful();

    expect(File::exists($this->snapshotPath().'/manifest.json'))->toBeTrue();

    $this->artisan('onneb:verify')->assertSuccessful();
});

it('permite indicar carpeta y empresa al exportar', function () {
    $path = $this->snapshotPath().'/otra';

    $this->artisan('onneb:export', ['path' => $path, '--empnit' => 'OTRA'])->assertSuccessful();

    expect(json_decode(File::get("{$path}/PRODUCTOS.json"), true))->toHaveCount(1);
});

it('reporta sin rastro de excepción si la extracción no es válida', function () {
    $this->artisan('onneb:verify')->assertFailed();
    $this->artisan('onneb:review')->assertFailed();
});

it('no escribe nada si falla la exportación', function () {
    config(['onneb.empnit' => 'NO-EXISTE']);

    $this->artisan('onneb:export')->assertFailed();

    expect(File::exists($this->snapshotPath()))->toBeFalse();
});

it('genera la revisión desde cualquier origen', function (string $source) {
    if ($source === 'snapshot') {
        $this->artisan('onneb:export')->assertSuccessful();
    }

    $this->artisan('onneb:review', ['--source' => $source])->assertSuccessful();

    expect(Storage::disk('local')->files('onneb'))->toHaveCount(1);
})->with(['snapshot', 'database']);

it('exige un importador registrado para migrar', function () {
    $this->artisan('onneb:migrate')->assertFailed();
});

it('solo escribe en el destino con --execute', function () {
    $importer = new class implements CatalogImporter
    {
        public array $runs = [];

        public function import(MappedCatalog $catalog, bool $dryRun): ImportResult
        {
            $this->runs[] = $dryRun;

            return new ImportResult(['Productos' => count($catalog->products)], $catalog->issues);
        }
    };

    app()->instance(CatalogImporter::class, $importer);

    $this->artisan('onneb:migrate', ['--source' => 'database'])->assertSuccessful();
    $this->artisan('onneb:migrate', ['--source' => 'database', '--execute' => true])->assertSuccessful();

    expect($importer->runs)->toBe([true, false]);
});

it('pide --force para escribir en producción', function () {
    $importer = new class implements CatalogImporter
    {
        public int $runs = 0;

        public function import(MappedCatalog $catalog, bool $dryRun): ImportResult
        {
            $this->runs++;

            return new ImportResult([], $catalog->issues);
        }
    };

    app()->instance(CatalogImporter::class, $importer);
    app()->detectEnvironment(fn () => 'production');

    $this->withoutMockingConsoleOutput();

    $cancelled = $this->artisan('onneb:migrate', ['--source' => 'database', '--execute' => true, '--no-interaction' => true]);

    expect($cancelled)->toBe(1)->and($importer->runs)->toBe(0);

    $forced = $this->artisan('onneb:migrate', ['--source' => 'database', '--execute' => true, '--force' => true]);

    expect($forced)->toBe(0)->and($importer->runs)->toBe(1);
});
