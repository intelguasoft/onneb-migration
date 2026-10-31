<?php

namespace RealsoftGt\OnneB\Data;

final readonly class MigrationResult
{
    /**
     * @param  string  $reportPath  Ruta absoluta del Excel de revisión.
     */
    public function __construct(
        public Catalog $catalog,
        public MappedCatalog $mapped,
        public ImportResult $import,
        public bool $dryRun,
        public string $reportPath,
    ) {}
}
