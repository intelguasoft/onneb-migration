<?php

namespace RealsoftGt\OnneB\Console;

use Illuminate\Console\Command;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\Snapshot\SnapshotWriter;

class ExportCommand extends Command
{
    protected $signature = 'onneb:export
        {path? : Carpeta de destino; por defecto onneb.sources.snapshot.path}
        {--empnit= : Empresa de OnneB; por defecto ONNEB_EMPNIT}';

    protected $description = 'Extrae el catálogo de SQL Server a archivos JSON verificables';

    public function handle(SnapshotWriter $writer): int
    {
        $path = $this->argument('path') ?? config('onneb.sources.snapshot.path');

        try {
            $tables = $writer->write($path, $this->option('empnit'));
        } catch (OnneBException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Tabla', 'Filas'], collect($tables)->map(fn (int $rows, string $table): array => [$table, $rows])->values()->all());
        $this->components->info("Extracción guardada en {$path}");
        $this->components->warn('Los archivos contienen costos y precios: no los incluya en repositorios públicos.');

        return self::SUCCESS;
    }
}
