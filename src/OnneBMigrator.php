<?php

namespace RealsoftGt\OnneB;

use RealsoftGt\OnneB\Contracts\CatalogImporter;
use RealsoftGt\OnneB\Data\MigrationResult;
use RealsoftGt\OnneB\Mapping\CatalogMapper;
use RealsoftGt\OnneB\Review\ReviewReport;
use RealsoftGt\OnneB\Sources\SourceManager;

/**
 * Orquesta la migración: lee del origen, mapea, importa y genera la revisión.
 * Simula salvo que se pida lo contrario.
 */
class OnneBMigrator
{
    public function __construct(
        private readonly SourceManager $sources,
        private readonly CatalogMapper $mapper,
        private readonly ReviewReport $report,
    ) {}

    public function run(CatalogImporter $importer, bool $dryRun = true, ?string $source = null): MigrationResult
    {
        $this->raiseMemoryLimit();

        $catalog = $this->sources->driver($source)->read();
        $mapped = $this->mapper->map($catalog);
        $import = $importer->import($mapped, $dryRun);
        $path = $this->report->store($catalog, $mapped, $import, $dryRun);

        return new MigrationResult($catalog, $mapped, $import, $dryRun, $path);
    }

    private function raiseMemoryLimit(): void
    {
        $limit = config('onneb.memory_limit');
        $current = ini_parse_quantity((string) ini_get('memory_limit'));

        if (filled($limit) && $current !== -1 && $current < ini_parse_quantity($limit)) {
            ini_set('memory_limit', $limit);
        }
    }
}
