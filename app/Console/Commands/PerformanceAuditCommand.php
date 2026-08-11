<?php

namespace App\Console\Commands;

use App\Support\Performance\PerformanceSnapshotService;
use Illuminate\Console\Command;

final class PerformanceAuditCommand extends Command
{
    protected $signature = 'operations:performance-audit {--json : Emit deterministic safe JSON}';

    protected $description = 'Read-only performance, query, cache, index and export governance audit';

    public function handle(PerformanceSnapshotService $service): int
    {
        $snapshot = $service->snapshot();
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        $this->info('Enterprise performance audit: read-only');
        $this->table(['Severity', 'Component', 'Category', 'Status', 'Finding'], array_map(static fn (array $finding): array => [$finding['severity'], $finding['component'], $finding['category'], $finding['status'], $finding['description']], $snapshot['findings']));
        $this->line('Checksum: '.$snapshot['checksum']);

        return self::SUCCESS;
    }
}
