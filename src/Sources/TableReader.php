<?php

namespace RealsoftGt\OnneB\Sources;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\Support\CatalogTables;

/**
 * Lee las tablas del catálogo de SQL Server. Solo ejecuta SELECT, siempre
 * filtrado por empresa, y omite las columnas binarias como PRODUCTOS.FOTO.
 */
class TableReader
{
    private const RUNTIME_CONNECTION = 'onneb';

    private const BINARY_TYPES = ['binary', 'varbinary', 'image', 'blob', 'timestamp', 'rowversion'];

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Repository $config,
    ) {}

    /**
     * @return array{empnit: string, tables: array<string, list<array<string, mixed>>>, columns: array<string, array<string, string>>}
     */
    public function read(?string $empnit = null): array
    {
        $empnit ??= $this->config->get('onneb.empnit');

        if (blank($empnit)) {
            throw new OnneBException('Falta ONNEB_EMPNIT: OnneB no se lee sin filtrar por empresa.');
        }

        $connection = $this->connection();
        $tables = [];
        $columns = [];

        $schema = $connection->getSchemaBuilder();

        foreach (CatalogTables::ORDER as $table => $order) {
            if (in_array($table, CatalogTables::OPTIONAL, true) && ! $schema->hasTable($table)) {
                continue;
            }

            $columns[$table] = $this->columns($connection, $table);

            $missing = array_diff(CatalogTables::REQUIRED_COLUMNS[$table], array_keys($columns[$table]));

            if ($missing !== []) {
                throw new OnneBException("La tabla {$table} no tiene las columnas: ".implode(', ', $missing).'.');
            }

            $tables[$table] = $connection->table($table)
                ->where('EMPNIT', $empnit)
                ->orderBy($order)
                ->get(array_keys($columns[$table]))
                ->map(fn (object $row): array => (array) $row)
                ->all();
        }

        if ($tables['PRODUCTOS'] === []) {
            throw new OnneBException("OnneB no devolvió productos para {$empnit}.");
        }

        if ($schema->hasTable('DOCUMENTOS') && $schema->hasTable('DOCPRODUCTOS') && $schema->hasTable('TIPODOCUMENTOS')) {
            $tables[CatalogTables::PURCHASES] = $this->purchases($connection, $empnit);
            $columns[CatalogTables::PURCHASES] = CatalogTables::PURCHASE_COLUMNS;
        }

        return ['empnit' => $empnit, 'tables' => $tables, 'columns' => $columns];
    }

    /**
     * Cuántas compras de cada producto se hicieron a cada proveedor y cuándo
     * fue la última. Son compras los documentos de tipo COM; los anulados
     * (STATUS = A) no cuentan. El proveedor está en DOCUMENTOS.CODCLIENTE.
     *
     * @return list<array{CODPROD: string, CODPROV: int, COMPRAS: int, ULTIMA_COMPRA: string}>
     */
    private function purchases(Connection $connection, string $empnit): array
    {
        $documents = $connection->table('DOCUMENTOS as d')
            ->join('DOCPRODUCTOS as p', function ($join): void {
                $join->on('p.EMPNIT', '=', 'd.EMPNIT')
                    ->on('p.CODDOC', '=', 'd.CODDOC')
                    ->on('p.CORRELATIVO', '=', 'd.CORRELATIVO');
            })
            ->where('d.EMPNIT', $empnit)
            ->whereIn('d.CODDOC', fn ($query) => $query->select('CODDOC')->from('TIPODOCUMENTOS')->where('EMPNIT', $empnit)->where('TIPODOC', 'COM'))
            ->where(fn ($query) => $query->whereNull('d.STATUS')->orWhere('d.STATUS', '<>', 'A'))
            ->whereNotNull('d.CODCLIENTE')
            ->distinct()
            ->get(['p.CODPROD', 'd.CODCLIENTE', 'd.CODDOC', 'd.CORRELATIVO', 'd.FECHA']);

        $summary = [];

        foreach ($documents as $document) {
            $product = trim((string) $document->CODPROD);
            $supplier = (int) $document->CODCLIENTE;
            $date = substr((string) $document->FECHA, 0, 10);
            $key = mb_strtoupper($product)."\0{$supplier}";

            $summary[$key] ??= ['CODPROD' => $product, 'CODPROV' => $supplier, 'COMPRAS' => 0, 'ULTIMA_COMPRA' => $date];
            $summary[$key]['COMPRAS']++;
            $summary[$key]['ULTIMA_COMPRA'] = max($summary[$key]['ULTIMA_COMPRA'], $date);
        }

        ksort($summary, SORT_STRING);

        return array_values($summary);
    }

    private function connection(): Connection
    {
        $name = $this->config->get('onneb.sources.database.connection');

        if (filled($name)) {
            return $this->database->connection($name);
        }

        $credentials = $this->config->get('onneb.sources.database.credentials');

        if (blank($credentials['host'] ?? null)) {
            throw new OnneBException('Falta ONNEB_DB_HOST u ONNEB_DB_CONNECTION para leer de SQL Server.');
        }

        // Se registra al leer, para que la aplicación no cargue una conexión
        // que solo usa la migración.
        $this->config->set('database.connections.'.self::RUNTIME_CONNECTION, $credentials);

        return $this->database->connection(self::RUNTIME_CONNECTION);
    }

    /**
     * @return array<string, string> Nombre de columna y tipo en la base.
     */
    private function columns(Connection $connection, string $table): array
    {
        return collect($connection->getSchemaBuilder()->getColumns($table))
            ->reject(fn (array $column): bool => in_array(strtolower($column['type_name']), self::BINARY_TYPES, true))
            ->mapWithKeys(fn (array $column): array => [$column['name'] => $column['type']])
            ->all();
    }
}
