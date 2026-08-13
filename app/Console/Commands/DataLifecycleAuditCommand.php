<?php

namespace App\Console\Commands;

use App\Support\DataLifecycle\DataLifecycleAuditService;
use Illuminate\Console\Command;

final class DataLifecycleAuditCommand extends Command
{
    protected $signature = 'operations:data-lifecycle-audit {--json : Emit deterministic safe JSON}';

    protected $description = 'Read-only enterprise retention, archive and disposal governance audit';

    public function handle(DataLifecycleAuditService $service): int
    {
        $snapshot = $service->snapshot();

        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Enterprise data lifecycle audit: read-only');
        $this->table(['Metric', 'Value'], [
            ['Policies / resource categories', $snapshot['policy_count'].' / '.count($snapshot['resource_categories'])],
            ['Retention eligible / legal holds', $snapshot['retention_eligible'].' / '.$snapshot['legal_holds']],
            ['Disposal eligible / blocked', $snapshot['disposal_eligible'].' / '.$snapshot['blocked_disposal']],
            ['Archive artifacts / integrity', $snapshot['archive_artifact_count'].' / '.$snapshot['archive_integrity']],
            ['Orphan artifact findings', $snapshot['orphan_artifact_findings']],
            ['Destructive disposal', $snapshot['destructive_disposal_enabled'] ? 'ENABLED' : 'DISABLED'],
        ]);
        $this->line('Policy checksum: '.$snapshot['policy_checksum']);

        return self::SUCCESS;
    }
}
