<?php

namespace RealsoftGt\OnneB\Sources;

use Illuminate\Filesystem\Filesystem;
use JsonException;
use RealsoftGt\OnneB\Contracts\CatalogSource;
use RealsoftGt\OnneB\Data\Catalog;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\Snapshot\SnapshotWriter;
use RealsoftGt\OnneB\Support\CatalogTables;

/**
 * Lee una extracción generada con `onneb:export`, sin conexión a SQL Server.
 *
 * Antes de usar los archivos verifica contra manifest.json que estén completos
 * y sin alterar: un catálogo a medias no debe llegar a la operación.
 */
class SnapshotSource implements CatalogSource
{
    public function __construct(
        private readonly string $path,
        private readonly Filesystem $files,
    ) {}

    public function read(): Catalog
    {
        $manifest = $this->decode($this->contents('manifest.json'), 'manifest.json');
        $format = $manifest['formato'] ?? 1;

        if ($format > SnapshotWriter::FORMAT) {
            throw new OnneBException("La extracción usa el formato {$format}: actualice realsoftgt/onneb-migration.");
        }

        $tables = [];

        foreach (CatalogTables::REQUIRED_COLUMNS as $table => $columns) {
            $tables[$table] = $this->table($table, $columns, $manifest['tablas'][$table] ?? null);
        }

        return Catalog::fromTables($tables, 'snapshot', $manifest['empnit'], $manifest['exportado_en']);
    }

    /**
     * @param  list<string>  $columns
     * @param  ?array{filas: int, sha256: string, columnas: array<string, string>}  $entry
     * @return list<array<string, mixed>>
     */
    private function table(string $table, array $columns, ?array $entry): array
    {
        if ($entry === null) {
            throw new OnneBException("manifest.json no incluye la tabla {$table}.");
        }

        $missing = array_diff($columns, array_keys($entry['columnas']));

        if ($missing !== []) {
            throw new OnneBException("La extracción de {$table} no trae las columnas: ".implode(', ', $missing).'.');
        }

        // El nombre sale de la tabla y no del manifiesto, para que un archivo
        // manipulado no pueda apuntar fuera de la carpeta.
        $file = "{$table}.json";
        $contents = $this->contents($file);

        // Git en Windows puede convertir los saltos de línea al clonar. Dentro
        // del JSON van escapados, así que normalizarlos no altera los datos.
        if (hash('sha256', str_replace("\r\n", "\n", $contents)) !== $entry['sha256']) {
            throw new OnneBException("{$file} no coincide con manifest.json: vuelva a generar la extracción.");
        }

        $rows = $this->decode($contents, $file);

        if (count($rows) !== $entry['filas']) {
            throw new OnneBException("{$file} trae ".count($rows)." filas y manifest.json declara {$entry['filas']}.");
        }

        return $rows;
    }

    private function contents(string $file): string
    {
        $path = "{$this->path}/{$file}";

        if (! $this->files->exists($path)) {
            throw new OnneBException("Falta {$path}: genere o copie la extracción con onneb:export.");
        }

        return $this->files->get($path);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decode(string $contents, string $file): array
    {
        try {
            return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OnneBException("{$file} no es un JSON válido.", previous: $exception);
        }
    }
}
