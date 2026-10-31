<?php

namespace RealsoftGt\OnneB\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\Sources\SnapshotSource;

class VerifyCommand extends Command
{
    protected $signature = 'onneb:verify {path? : Carpeta de la extracción; por defecto onneb.sources.snapshot.path}';

    protected $description = 'Verifica que una extracción esté completa y sin alterar';

    public function handle(Filesystem $files): int
    {
        $path = $this->argument('path') ?? config('onneb.sources.snapshot.path');

        try {
            $catalog = (new SnapshotSource($path, $files))->read();
        } catch (OnneBException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Extracción válida de {$catalog->empnit}, del {$catalog->extractedAt}.");
        $this->components->twoColumnDetail('Productos habilitados', (string) count($catalog->products));
        $this->components->twoColumnDetail('Precios', (string) count($catalog->prices));
        $this->components->twoColumnDetail('Marcas', (string) count($catalog->brands));

        return self::SUCCESS;
    }
}
