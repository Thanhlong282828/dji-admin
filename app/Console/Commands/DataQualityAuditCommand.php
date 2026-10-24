<?php

namespace App\Console\Commands;

use App\Support\DataQuality\DataQualityAuditService;
use Illuminate\Console\Command;

final class DataQualityAuditCommand extends Command
{
    protected $signature = 'operations:data-quality-audit {--json : Emit deterministic safe JSON}';

    protected $description = 'Audit aggregate cross-domain data quality without mutating records.';

    public function handle(DataQualityAuditService $service): int
    {
        $snapshot = $service->snapshot();
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->table(['Metric', 'Value'], [
                ['environment', $snapshot['environment']],
                ['configured_database', $snapshot['configured_database']],
                ['actual_database', $snapshot['actual_database']],
                ['database_purpose', $snapshot['database_purpose']],
                ['database_matches', $snapshot['database_matches'] ? 'YES' : 'NO'],
                ['rules', (string) $snapshot['rules']], ['passed', (string) $snapshot['passed']],
                ['failed', (string) $snapshot['failed']], ['warnings', (string) $snapshot['warnings']],
                ['orphans', (string) $snapshot['orphans']], ['duplicates', (string) $snapshot['duplicates']],
                ['cross_tenant', (string) $snapshot['cross_tenant']], ['score', (string) $snapshot['score']],
                ['checksum', $snapshot['checksum']],
            ]);
        }

        return self::SUCCESS;
    }
}
