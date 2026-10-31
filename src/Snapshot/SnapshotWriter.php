<?php

namespace RealsoftGt\OnneB\Snapshot;

use Illuminate\Filesystem\Filesystem;
use RealsoftGt\OnneB\Sources\TableReader;

/**
 * Escribe la extracción de OnneB: un JSON por tabla, con una fila por línea
 * para comparar extracciones, y un manifest.json con filas, columnas y sha256.
 */
class SnapshotWriter
{
    public const FORMAT = 1;

    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function __construct(
        private readonly TableReader $reader,
        private readonly Filesystem $files,
    ) {}

    /**
     * @return array<string, int> Filas por tabla.
     */
    public function write(string $path, ?string $empnit = null): array
    {
        $extraction = $this->reader->read($empnit);
        $contents = [];
        $tables = [];

        foreach ($extraction['tables'] as $table => $rows) {
            $contents["{$table}.json"] = $this->encodeRows($rows);
            $tables[$table] = [
                'archivo' => "{$table}.json",
                'filas' => count($rows),
                'sha256' => hash('sha256', $contents["{$table}.json"]),
                'columnas' => $extraction['columns'][$table],
            ];
        }

        $manifest = json_encode([
            'formato' => self::FORMAT,
            'origen' => 'OnneB',
            'empnit' => $extraction['empnit'],
            'exportado_en' => now()->toIso8601String(),
            'tablas' => $tables,
        ], self::JSON_FLAGS | JSON_PRETTY_PRINT)."\n";

        $this->files->ensureDirectoryExists($path);

        // Sin manifiesto, una escritura interrumpida no se puede leer.
        $this->files->delete("{$path}/manifest.json");

        foreach ($contents as $name => $content) {
            $this->files->put("{$path}/{$name}", $content);
        }

        $this->files->put("{$path}/manifest.json", $manifest);

        return array_map(fn (array $table): int => $table['filas'], $tables);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function encodeRows(array $rows): string
    {
        $lines = array_map(fn (array $row): string => json_encode($row, self::JSON_FLAGS), $rows);

        return "[\n".implode(",\n", $lines)."\n]\n";
    }
}
