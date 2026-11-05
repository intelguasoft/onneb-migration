<?php

namespace RealsoftGt\OnneB\Console;

use Illuminate\Console\Command;
use RealsoftGt\OnneB\Exceptions\OnneBException;
use RealsoftGt\OnneB\OnneBMigrator;
use RealsoftGt\OnneB\Support\NullImporter;

class ReviewCommand extends Command
{
    protected $signature = 'onneb:review {--source= : Origen: snapshot o database}';

    protected $description = 'Genera el Excel de revisión sin escribir en ningún destino';

    public function handle(OnneBMigrator $migrator): int
    {
        try {
            $result = $migrator->run(new NullImporter, dryRun: true, source: $this->option('source'));
        } catch (OnneBException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result->import->summary as $label => $value) {
            $this->components->twoColumnDetail($label, (string) $value);
        }

        $this->components->info("Atención: {$result->attentionPath}");
        $this->components->info("Revisión completa: {$result->reportPath}");

        return self::SUCCESS;
    }
}
