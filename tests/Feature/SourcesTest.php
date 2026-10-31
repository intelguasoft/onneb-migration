<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RealsoftGt\OnneB\Contracts\CatalogSource;
use RealsoftGt\OnneB\Data\Catalog;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\Mapping\CatalogMapper;
use RealsoftGt\OnneB\Snapshot\SnapshotWriter;
use RealsoftGt\OnneB\Sources\DatabaseSource;
use RealsoftGt\OnneB\Sources\SnapshotSource;
use RealsoftGt\OnneB\Sources\SourceManager;

beforeEach(function () {
    File::deleteDirectory($this->snapshotPath());
    $this->createOnneBTables();
});

afterEach(function () {
    File::deleteDirectory($this->snapshotPath());
});

it('lee de SQL Server solo la empresa y sus productos habilitados', function () {
    $catalog = app(SourceManager::class)->driver('database')->read();

    expect($catalog)->toBeInstanceOf(Catalog::class)
        ->and($catalog->origin)->toBe('database')
        ->and($catalog->empnit)->toBe('EMPRESA')
        ->and(array_column($catalog->products, 'CODPROD'))->toBe(['1005', '1006'])
        ->and($catalog->products[0])->not->toHaveKey('FOTO')
        ->and($catalog->prices)->toHaveCount(3)
        ->and($catalog->brands)->toBe([1 => 'MK'])
        ->and($catalog->stock)->toBe(['1005' => 42.0]);
});

it('entrega el mismo catálogo y mapeo desde archivos que desde SQL Server', function () {
    $manager = app(SourceManager::class);

    app(SnapshotWriter::class)->write($this->snapshotPath());

    $direct = $manager->driver('database')->read();
    $snapshot = $manager->driver('snapshot')->read();

    expect($snapshot->origin)->toBe('snapshot')
        ->and($snapshot->products)->toBe($direct->products)
        ->and($snapshot->prices)->toBe($direct->prices)
        ->and($snapshot->brands)->toBe($direct->brands)
        ->and($snapshot->stock)->toBe($direct->stock)
        ->and(app(CatalogMapper::class)->map($snapshot))->toEqual(app(CatalogMapper::class)->map($direct));
});

it('usa el origen configurado y permite registrar orígenes propios', function () {
    $manager = app(SourceManager::class);

    config(['onneb.source' => 'database']);
    expect($manager->getDefaultDriver())->toBe('database')
        ->and($manager->driver())->toBeInstanceOf(DatabaseSource::class);

    config(['onneb.source' => 'snapshot']);
    expect($manager->driver('snapshot'))->toBeInstanceOf(SnapshotSource::class);

    $custom = new class implements CatalogSource
    {
        public function read(): Catalog
        {
            return new Catalog([], [], [], [], 'api', 'EMPRESA', '2026-01-15T08:00:00-06:00');
        }
    };

    $manager->extend('api', fn () => $custom);

    expect($manager->driver('api')->read()->origin)->toBe('api');
});

it('no lee SQL Server sin empresa ni servidor', function (array $config, string $message) {
    config($config);

    expect(fn () => app(DatabaseSource::class)->read())->toThrow(OnneBException::class, $message);
})->with([
    'sin empresa' => [['onneb.empnit' => null], 'ONNEB_EMPNIT'],
    'sin servidor' => [['onneb.sources.database.connection' => null, 'onneb.sources.database.credentials.host' => null], 'ONNEB_DB_HOST'],
    'empresa sin productos' => [['onneb.empnit' => 'NO-EXISTE'], 'no devolvió productos'],
]);

it('avisa si a OnneB le falta una columna necesaria', function () {
    Schema::connection('fixture')->table('MARCAS', function ($table) {
        $table->dropColumn('DESMARCA');
    });

    expect(fn () => app(DatabaseSource::class)->read())->toThrow(OnneBException::class, 'DESMARCA');
});
