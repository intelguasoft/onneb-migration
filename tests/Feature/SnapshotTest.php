<?php

use Illuminate\Support\Facades\File;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\Snapshot\SnapshotWriter;
use RealsoftGt\OnneB\Sources\SourceManager;

function manifest(string $path): array
{
    return json_decode(File::get("{$path}/manifest.json"), true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    File::deleteDirectory($this->snapshotPath());
    $this->createOnneBTables();
    app(SnapshotWriter::class)->write($this->snapshotPath());
});

afterEach(function () {
    File::deleteDirectory($this->snapshotPath());
});

it('guarda toda la empresa con una fila por línea y sin binarios', function () {
    $contents = File::get($this->snapshotPath().'/PRODUCTOS.json');
    $rows = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    expect(substr_count($contents, "\n"))->toBe(5)
        ->and(array_column($rows, 'CODPROD'))->toBe(['1005', '1006', '2000'])
        ->and($rows[2]['DESPROD'])->toBe('AÑIL "AZUL" (MK)')
        ->and($rows[2])->not->toHaveKey('FOTO');
});

it('describe cada tabla en el manifiesto', function () {
    $manifest = manifest($this->snapshotPath());
    $products = $manifest['tablas']['PRODUCTOS'];

    expect($manifest['formato'])->toBe(SnapshotWriter::FORMAT)
        ->and($manifest['empnit'])->toBe('EMPRESA')
        ->and($products['filas'])->toBe(3)
        ->and($products['sha256'])->toBe(hash('sha256', File::get($this->snapshotPath().'/PRODUCTOS.json')))
        ->and($products['columnas'])->toHaveKey('CODPROD')
        ->and($products['columnas'])->not->toHaveKey('FOTO');
});

it('acepta saltos de línea convertidos por Git en Windows', function () {
    $path = $this->snapshotPath().'/PRECIOS.json';
    File::put($path, str_replace("\n", "\r\n", File::get($path)));

    expect(app(SourceManager::class)->driver('snapshot')->read()->prices)->toHaveCount(3);
});

it('lee extracciones anteriores sin versión de formato', function () {
    $manifest = manifest($this->snapshotPath());
    unset($manifest['formato']);
    File::put($this->snapshotPath().'/manifest.json', json_encode($manifest));

    expect(app(SourceManager::class)->driver('snapshot')->read()->products)->toHaveCount(2);
});

it('rechaza una extracción incompleta o alterada', function (Closure $damage, string $message) {
    $damage($this->snapshotPath());

    expect(fn () => app(SourceManager::class)->driver('snapshot')->read())->toThrow(OnneBException::class, $message);
})->with([
    'sin manifiesto' => [fn (string $path) => File::delete("{$path}/manifest.json"), 'manifest.json'],
    'sin tabla' => [fn (string $path) => File::delete("{$path}/PRECIOS.json"), 'PRECIOS.json'],
    'archivo modificado' => [
        fn (string $path) => File::put("{$path}/PRECIOS.json", str_replace('180', '1', File::get("{$path}/PRECIOS.json"))),
        'no coincide con manifest.json',
    ],
    'manifiesto dañado' => [fn (string $path) => File::put("{$path}/manifest.json", '{'), 'no es un JSON válido'],
    'columna faltante' => [function (string $path) {
        $manifest = manifest($path);
        unset($manifest['tablas']['MARCAS']['columnas']['DESMARCA']);
        File::put("{$path}/manifest.json", json_encode($manifest));
    }, 'DESMARCA'],
    'tabla fuera del manifiesto' => [function (string $path) {
        $manifest = manifest($path);
        unset($manifest['tablas']['INVSALDO']);
        File::put("{$path}/manifest.json", json_encode($manifest));
    }, 'INVSALDO'],
    'formato futuro' => [function (string $path) {
        File::put("{$path}/manifest.json", json_encode(['formato' => 99] + manifest($path)));
    }, 'actualice'],
]);

it('ignora rutas del manifiesto para no leer fuera de la carpeta', function () {
    $path = $this->snapshotPath();
    $manifest = manifest($path);
    $manifest['tablas']['PRECIOS']['archivo'] = '../secreto.json';
    File::put("{$path}/manifest.json", json_encode($manifest));

    expect(app(SourceManager::class)->driver('snapshot')->read()->prices)->toHaveCount(3);
});
