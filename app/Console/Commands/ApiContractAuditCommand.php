<?php

namespace App\Console\Commands;

use App\Support\ApiContract\ApiContractAuditService;
use Illuminate\Console\Command;

final class ApiContractAuditCommand extends Command
{
    protected $signature = 'operations:api-contract-audit {--json : Emit deterministic safe JSON}';

    protected $description = 'Read-only route and OpenAPI contract governance audit';

    public function handle(ApiContractAuditService $service): int
    {
        $snapshot = $service->snapshot();
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $snapshot['status'] === 'pass' ? self::SUCCESS : self::FAILURE;
        }
        $this->info('Enterprise API contract audit: read-only');
        $this->table(['Metric', 'Value'], [
            ['Actual paths / operations', $snapshot['actual_paths'].' / '.$snapshot['actual_operations']],
            ['Documented paths / operations', $snapshot['documented_paths'].' / '.$snapshot['documented_operations']],
            ['Undocumented / orphan', count($snapshot['undocumented_operations']).' / '.count($snapshot['orphan_operations'])],
            ['Duplicate operationIds', count($snapshot['duplicate_operation_ids'])],
            ['Breaking / additive', count($snapshot['breaking_findings']).' / '.count($snapshot['additive_findings'])],
            ['Security scheme', $snapshot['security_scheme']],
        ]);
        $this->line('Checksum: '.$snapshot['checksum']);

        return $snapshot['status'] === 'pass' ? self::SUCCESS : self::FAILURE;
    }
}
