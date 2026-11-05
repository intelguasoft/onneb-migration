<?php

namespace RealsoftGt\OnneB\Data;

final readonly class MigrationResult
{
    /**
     * @param  string  $reportPath  Ruta absoluta del Excel de revisión completo.
     * @param  ?string  $attentionPath  Ruta absoluta del Excel con solo lo que requiere a una persona.
     */
    public function __construct(
        public Catalog $catalog,
        public MappedCatalog $mapped,
        public ImportResult $import,
        public bool $dryRun,
        public string $reportPath,
        public ?string $attentionPath = null,
    ) {}
}
