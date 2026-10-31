<?php

namespace RealsoftGt\OnneB\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RealsoftGt\OnneB\OnneBServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ExcelServiceProvider::class, OnneBServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.connections.fixture', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('onneb.empnit', 'EMPRESA');
        $app['config']->set('onneb.sources.database.connection', 'fixture');
        $app['config']->set('onneb.sources.snapshot.path', $this->snapshotPath());
    }

    protected function snapshotPath(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'onneb-migration-tests'.DIRECTORY_SEPARATOR.getmypid();
    }

    /**
     * Base de muestra con la estructura de las tablas de OnneB.
     */
    protected function createOnneBTables(): void
    {
        $schema = Schema::connection('fixture');

        $schema->create('PRODUCTOS', function (Blueprint $table) {
            $table->string('EMPNIT');
            $table->string('CODPROD');
            $table->string('CODPROD2')->nullable();
            $table->string('DESPROD');
            $table->string('DESPROD2')->nullable();
            $table->string('DESPROD3')->nullable();
            $table->integer('UXC');
            $table->decimal('COSTO', 18, 4);
            $table->integer('CODMARCA');
            $table->integer('INVMINIMO');
            $table->integer('INVMAXIMO');
            $table->integer('EXENTO');
            $table->string('TIPOPROD');
            $table->string('HABILITADO');
            $table->binary('FOTO')->nullable();
        });

        $schema->create('PRECIOS', function (Blueprint $table) {
            $table->integer('ID');
            $table->string('EMPNIT');
            $table->string('CODPROD');
            $table->string('CODMEDIDA');
            $table->integer('EQUIVALE');
            $table->decimal('COSTO', 18, 4);
            $table->decimal('PRECIO', 18, 2);
            $table->decimal('MAYOREOA', 18, 2);
            $table->decimal('MAYOREOB', 18, 2);
            $table->decimal('MAYOREOC', 18, 2);
        });

        $schema->create('MARCAS', function (Blueprint $table) {
            $table->string('EMPNIT');
            $table->integer('CODMARCA');
            $table->string('DESMARCA');
        });

        $schema->create('INVSALDO', function (Blueprint $table) {
            $table->string('EMPNIT');
            $table->string('CODPROD');
            $table->integer('SALDO');
        });

        $product = fn (string $code, string $name, string $enabled, string $empnit = 'EMPRESA', ?string $photo = null): array => [
            'EMPNIT' => $empnit, 'CODPROD' => $code, 'CODPROD2' => $code, 'DESPROD' => $name, 'DESPROD2' => $name, 'DESPROD3' => $name,
            'UXC' => 1, 'COSTO' => '1.0000', 'CODMARCA' => 1, 'INVMINIMO' => 0, 'INVMAXIMO' => 0, 'EXENTO' => 0,
            'TIPOPROD' => 'P', 'HABILITADO' => $enabled, 'FOTO' => $photo,
        ];

        $price = fn (int $id, string $code, string $unit, int $factor, string $amount, string $wholesale, string $empnit = 'EMPRESA'): array => [
            'ID' => $id, 'EMPNIT' => $empnit, 'CODPROD' => $code, 'CODMEDIDA' => $unit, 'EQUIVALE' => $factor, 'COSTO' => '1.0000',
            'PRECIO' => $amount, 'MAYOREOA' => $wholesale, 'MAYOREOB' => $amount, 'MAYOREOC' => $amount,
        ];

        $onneb = DB::connection('fixture');

        $onneb->table('PRODUCTOS')->insert([
            $product('2000', 'AÑIL "AZUL" (MK)', 'NO', photo: "\xFF\xD8"),
            $product('1005', 'AMOXICILINA X 100 (MK)x', 'SI'),
            $product('1006', 'SIN PRECIO (MK)', ' si '),
            $product('9999', 'AJENO', 'SI', 'OTRA'),
        ]);

        $onneb->table('PRECIOS')->insert([
            $price(1, '1005', 'UNIDAD', 1, '2.00', '2.00'),
            $price(2, '1005', 'CAJA', 100, '180.00', '170.00'),
            $price(3, '1006', 'UNIDAD', 1, '0.00', '0.00'),
            $price(4, '9999', 'UNIDAD', 1, '9.00', '9.00', 'OTRA'),
        ]);

        $onneb->table('MARCAS')->insert(['EMPNIT' => 'EMPRESA', 'CODMARCA' => 1, 'DESMARCA' => 'MK']);

        $onneb->table('INVSALDO')->insert([
            ['EMPNIT' => 'EMPRESA', 'CODPROD' => '1005', 'SALDO' => 40],
            ['EMPNIT' => 'EMPRESA', 'CODPROD' => '1005 ', 'SALDO' => 2],
        ]);
    }
}
