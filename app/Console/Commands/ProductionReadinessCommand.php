<?php

namespace App\Console\Commands;

use App\Support\GoLive\ProductionReadinessService;
use Illuminate\Console\Command;

final class ProductionReadinessCommand extends Command
{
    protected $signature = 'operations:production-readiness {--json : Emit machine-readable JSON}';

    protected $description = 'Inspect production go-live readiness without deploying, migrating or exposing secrets';

    public function handle(ProductionReadinessService $service): int
    {
        $snapshot = $service->snapshot();
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->components->info('Classification: '.$snapshot['classification']);
            foreach ($snapshot['checks'] as $check) {
                $this->line(sprintf('[%s] %s — %s', $check['status'], $check['label'], $check['detail']));
            }
            foreach ($snapshot['blockers'] as $blocker) {
                $this->components->warn($blocker['dependency'].': '.$blocker['detail']);
            }
        }

        return self::SUCCESS;
    }
}
