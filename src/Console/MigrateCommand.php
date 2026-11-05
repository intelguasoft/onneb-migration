<?php

namespace RealsoftGt\OnneB\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use RealsoftGt\OnneB\Contracts\CatalogImporter;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\OnneBMigrator;

class MigrateCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'onneb:migrate
        {--source= : Origen: snapshot o database}
        {--execute : Escribe en el destino; sin esta opción solo simula}
        {--force : Permite escribir en producción sin confirmar}';

    protected $description = 'Importa el catálogo al destino registrado como CatalogImporter';

    public function handle(OnneBMigrator $migrator): int
    {
        if (! $this->laravel->bound(CatalogImporter::class)) {
            $this->components->error('Registre su implementación de CatalogImporter en el contenedor.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');

        if ($execute && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        try {
            $result = $migrator->run($this->laravel->make(CatalogImporter::class), ! $execute, $this->option('source'));
        } catch (OnneBException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result->import->summary as $label => $value) {
            $this->components->twoColumnDetail($label, (string) $value);
        }

        $this->components->info($execute ? 'Catálogo importado.' : 'Simulación: no se escribió nada.');
        $this->components->info("Atención: {$result->attentionPath}");
        $this->components->info("Revisión completa: {$result->reportPath}");

        return self::SUCCESS;
    }
}
