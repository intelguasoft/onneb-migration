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
            $table->integer('CODCLADOS')->nullable();
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

        $schema->create('PROVEEDORES', function (Blueprint $table) {
            $table->string('EMPNIT');
            $table->integer('CODPROV');
            $table->string('EMPRESA');
            $table->string('RAZONSOCIAL');
            $table->string('DIRECCION');
            $table->string('TELEMPRESA');
            $table->string('CONTACTO');
            $table->string('TELCONTACTO');
            $table->string('NIT');
            $table->float('SALDO')->nullable();
        });

        $schema->create('TIPODOCUMENTOS', function (Blueprint $table) {
            $table->string('EMPNIT');
            $table->string('CODDOC');
            $table->string('TIPODOC');
        });

        $schema->create('DOCUMENTOS', function (Blueprint $table) {
            $table->string('EMPNIT');
            $table->string('CODDOC');
            $table->integer('CORRELATIVO');
            $table->integer('CODCLIENTE')->nullable();
            $table->date('FECHA');
            $table->string('STATUS')->nullable();
        });

        $schema->create('DOCPRODUCTOS', function (Blueprint $table) {
            $table->string('EMPNIT');
            $table->string('CODDOC');
            $table->integer('CORRELATIVO');
            $table->string('CODPROD');
        });

        $product = fn (string $code, string $name, string $enabled, string $empnit = 'EMPRESA', ?string $photo = null, ?int $supplier = null): array => [
            'EMPNIT' => $empnit, 'CODPROD' => $code, 'CODPROD2' => $code, 'DESPROD' => $name, 'DESPROD2' => $name, 'DESPROD3' => $name,
            'UXC' => 1, 'COSTO' => '1.0000', 'CODMARCA' => 1, 'INVMINIMO' => 0, 'INVMAXIMO' => 0, 'EXENTO' => 0,
            'TIPOPROD' => 'P', 'HABILITADO' => $enabled, 'CODCLADOS' => $supplier, 'FOTO' => $photo,
        ];

        $price = fn (int $id, string $code, string $unit, int $factor, string $amount, string $wholesale, string $empnit = 'EMPRESA'): array => [
            'ID' => $id, 'EMPNIT' => $empnit, 'CODPROD' => $code, 'CODMEDIDA' => $unit, 'EQUIVALE' => $factor, 'COSTO' => '1.0000',
            'PRECIO' => $amount, 'MAYOREOA' => $wholesale, 'MAYOREOB' => $amount, 'MAYOREOC' => $amount,
        ];

        $onneb = DB::connection('fixture');

        $onneb->table('PRODUCTOS')->insert([
            $product('2000', 'AÑIL "AZUL" (MK)', 'NO', photo: "\xFF\xD8"),
            $product('1005', 'AMOXICILINA X 100 (MK)x', 'SI', supplier: 10),
            $product('1006', 'SIN PRECIO (MK)', ' si ', supplier: 99),
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

        $supplier = fn (int $code, string $name, string $legalName, string $phone, string $terms, string $taxId, float $balance, string $empnit = 'EMPRESA'): array => [
            'EMPNIT' => $empnit, 'CODPROV' => $code, 'EMPRESA' => $name, 'RAZONSOCIAL' => $legalName, 'DIRECCION' => 'ZONA 1',
            'TELEMPRESA' => $phone, 'CONTACTO' => 'SN', 'TELCONTACTO' => $terms, 'NIT' => $taxId, 'SALDO' => $balance,
        ];

        $onneb->table('PROVEEDORES')->insert([
            $supplier(10, 'DROGUERIA UNO', 'DROGUERIA UNO, S.A.', '2222-3333', '30 DIAS CREDITO', '576937-K', 0),
            $supplier(20, 'DISTRIBUIDORA DOS', 'DISTRIBUIDORA DOS', 'SN', 'SN', 'CF', -150.5),
            $supplier(30, 'AJENO', 'AJENO', 'SN', 'CONTADO', 'CF', 0, 'OTRA'),
        ]);

        $onneb->table('TIPODOCUMENTOS')->insert([
            ['EMPNIT' => 'EMPRESA', 'CODDOC' => 'COMP', 'TIPODOC' => 'COM'],
            ['EMPNIT' => 'EMPRESA', 'CODDOC' => 'FAC', 'TIPODOC' => 'FAC'],
        ]);

        $document = fn (string $type, int $number, int $supplier, string $date, string $status = 'O'): array => [
            'EMPNIT' => 'EMPRESA', 'CODDOC' => $type, 'CORRELATIVO' => $number, 'CODCLIENTE' => $supplier, 'FECHA' => $date, 'STATUS' => $status,
        ];

        $onneb->table('DOCUMENTOS')->insert([
            $document('COMP', 1, 20, '2026-10-01'),
            $document('COMP', 2, 20, '2026-11-05'),
            $document('COMP', 3, 10, '2026-09-01', 'A'),
            $document('FAC', 1, 20, '2026-11-06'),
        ]);

        $line = fn (string $type, int $number, string $code): array => ['EMPNIT' => 'EMPRESA', 'CODDOC' => $type, 'CORRELATIVO' => $number, 'CODPROD' => $code];

        $onneb->table('DOCPRODUCTOS')->insert([
            $line('COMP', 1, '1005'),
            $line('COMP', 2, '1005'),
            $line('COMP', 2, '1005'),
            $line('COMP', 3, '1006'),
            $line('FAC', 1, '1005'),
        ]);
    }
}
