<?php

namespace RealsoftGt\OnneB;

use RealsoftGt\OnneB\Contracts\CatalogImporter;
use RealsoftGt\OnneB\Data\MigrationResult;
use RealsoftGt\OnneB\Mapping\CatalogMapper;
use RealsoftGt\OnneB\Review\ReviewReport;
use RealsoftGt\OnneB\Sources\SourceManager;
use RealsoftGt\OnneB\Support\MemoryLimit;

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
        MemoryLimit::raise();

        $catalog = $this->sources->driver($source)->read();
        $mapped = $this->mapper->map($catalog);
        $import = $importer->import($mapped, $dryRun);
        $path = $this->report->store($catalog, $mapped, $import, $dryRun);

        return new MigrationResult($catalog, $mapped, $import, $dryRun, $path);
    }
}
