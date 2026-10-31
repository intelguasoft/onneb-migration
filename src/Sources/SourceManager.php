<?php

namespace RealsoftGt\OnneB\Sources;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Manager;

/**
 * Resuelve el origen del catálogo. Admite orígenes propios con `extend()`.
 *
 * @method \RealsoftGt\OnneB\Data\Catalog read()
 */
class SourceManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('onneb.source', 'snapshot');
    }

    protected function createDatabaseDriver(): DatabaseSource
    {
        return $this->container->make(DatabaseSource::class);
    }

    protected function createSnapshotDriver(): SnapshotSource
    {
        return new SnapshotSource(
            $this->config->get('onneb.sources.snapshot.path'),
            $this->container->make(Filesystem::class),
        );
    }
}
