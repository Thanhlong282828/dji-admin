<?php

namespace Modules\Document\Console\Commands;

use Illuminate\Console\Command;
use Modules\Document\Application\Services\DocumentStorageReconciliationService;

final class ReconcileDocumentStorageCommand extends Command
{
    protected $signature = 'documents:storage-reconcile {--company-id=} {--limit=500} {--max-seconds=30}';

    protected $description = 'Dry-run tenant-scoped document metadata and object reconciliation.';

    public function handle(DocumentStorageReconciliationService $service): int
    {
        $companyId = filter_var($this->option('company-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($companyId)) {
            $this->error('--company-id is required and must be a positive integer.');

            return self::INVALID;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 500;
        $maxSeconds = filter_var($this->option('max-seconds'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 30;
        $result = $service->audit($companyId, (int) $limit, (int) $maxSeconds);

        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $result['anomalies'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
