<?php

namespace Modules\Document\Console\Commands;

use Illuminate\Console\Command;
use Modules\Document\Application\Services\DocumentStorageTenantReconciliationService;

final class ReconcileTenantDocumentStorageCommand extends Command
{
    protected $signature = 'documents:storage-reconcile-tenants';

    protected $description = 'Dry-run active-company document storage reconciliation in bounded cursor batches.';

    public function handle(DocumentStorageTenantReconciliationService $service): int
    {
        $result = $service->runBatch(
            (int) config('document.reconciliation.companies_per_run', 25),
            (int) config('document.reconciliation.max_items', 500),
            (int) config('document.reconciliation.max_seconds', 30),
            (int) config('document.reconciliation.batch_max_seconds', 240),
        );
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $result['companies_with_anomalies'] === 0 && $result['failed_companies'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
