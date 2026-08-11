<?php

namespace App\Console\Commands;

use App\Support\Architecture\ArchitectureAuditService;
use Illuminate\Console\Command;

final class ArchitectureAuditCommand extends Command
{
    protected $signature = 'operations:architecture-audit {--json : Emit safe deterministic JSON}';

    protected $description = 'Read-only enterprise module, layer, CQRS and dependency governance audit';

    public function handle(ArchitectureAuditService $service): int
    {
        $snapshot = $service->snapshot();
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        $this->info('Enterprise architecture audit: read-only');
        $this->table(['Metric', 'Value'], [
            ['Modules', $snapshot['modules']],
            ['Declared dependency edges', $snapshot['declared_edges']],
            ['Observed dependency edges', $snapshot['observed_edges']],
            ['Cycles', count($snapshot['cycles'])],
            ['Undeclared dependencies', count($snapshot['undeclared_edges'])],
            ['Layer violations', count($snapshot['layer_violations'])],
            ['CQRS violations', count($snapshot['cqrs_violations'])],
            ['External-boundary violations', count($snapshot['external_boundary_violations'])],
        ]);
        $this->line('Checksum: '.$snapshot['checksum']);

        return self::SUCCESS;
    }
}
