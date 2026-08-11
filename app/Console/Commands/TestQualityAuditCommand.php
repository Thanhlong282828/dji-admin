<?php

namespace App\Console\Commands;

use App\Support\TestGovernance\TestQualitySnapshotService;
use Illuminate\Console\Command;

final class TestQualityAuditCommand extends Command
{
    protected $signature = 'operations:test-quality {--json : Emit safe JSON}';

    protected $description = 'Read-only enterprise test taxonomy, invariant and isolation audit';

    public function handle(TestQualitySnapshotService $service): int
    {
        $snapshot = $service->snapshot();
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        $this->info('Enterprise test-quality audit: read-only');
        $this->table(['Metric', 'Value'], [
            ['PHPUnit files', $snapshot['phpunit_files']],
            ['Browser files', $snapshot['browser_files']],
            ['Critical invariants', count($snapshot['critical_invariants'])],
            ['Covered invariants', $snapshot['critical_covered']],
            ['Coverage gaps', $snapshot['critical_gaps']],
            ['Known orphan workers', $snapshot['runtime']['orphan_workers']],
        ]);
        $this->line('Checksum: '.$snapshot['checksum']);

        return $snapshot['critical_gaps'] === 0 && $snapshot['runtime']['orphan_workers'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
