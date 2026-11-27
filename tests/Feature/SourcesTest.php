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

it('lee los proveedores y resume las compras sin anuladas ni ventas', function () {
    $catalog = app(SourceManager::class)->driver('database')->read();

    expect(array_column($catalog->suppliers, 'CODPROV'))->toEqual([10, 20])
        ->and($catalog->purchases)->toEqual([
            ['CODPROD' => '1005', 'CODPROV' => 20, 'COMPRAS' => 2, 'ULTIMA_COMPRA' => '2026-11-05'],
        ]);
});

it('liga cada producto con su proveedor principal y los demás a los que se le compró', function () {
    config(['onneb.mapping.primary_supplier_column' => 'codclados']);

    $mapped = app(CatalogMapper::class)->map(app(SourceManager::class)->driver('database')->read());
    [$amoxicillin, $noPrice] = $mapped->products;

    expect($amoxicillin['suppliers'])->toBe([
        ['code' => '10', 'is_primary' => true, 'purchases' => 0, 'last_purchase' => null],
        ['code' => '20', 'is_primary' => false, 'purchases' => 2, 'last_purchase' => '2026-11-05'],
    ])
        ->and($noPrice['suppliers'])->toBe([])
        ->and(collect($mapped->issues)->where('codprod', '1006')->pluck('incidencia'))->toContain('proveedor_inexistente');
});

it('sin columna de proveedor principal solo liga a quienes se les compró', function () {
    $mapped = app(CatalogMapper::class)->map(app(SourceManager::class)->driver('database')->read());

    expect(array_column($mapped->products[0]['suppliers'], 'code'))->toBe(['20'])
        ->and(collect($mapped->issues)->pluck('incidencia'))->not->toContain('proveedor_inexistente');
});

it('migra sin proveedores una base de OnneB que no tiene sus tablas', function () {
    foreach (['PROVEEDORES', 'DOCUMENTOS'] as $table) {
        Schema::connection('fixture')->drop($table);
    }

    $catalog = app(SourceManager::class)->driver('database')->read();

    expect($catalog->suppliers)->toBe([])
        ->and($catalog->purchases)->toBe([]);
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
        ->and($snapshot->suppliers)->toEqual($direct->suppliers)
        ->and($snapshot->purchases)->toEqual($direct->purchases)
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
