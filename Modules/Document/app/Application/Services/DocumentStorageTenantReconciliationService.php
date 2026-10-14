<?php

namespace Modules\Document\Application\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class DocumentStorageTenantReconciliationService
{
    private const CURSOR_KEY = 'documents:storage-reconciliation:active-company-cursor';

    public function __construct(
        private ConnectionInterface $db,
        private Repository $cache,
        private LoggerInterface $logger,
        private DocumentStorageReconciliationService $reconciliation,
    ) {}

    /** @return array{dry_run:true,processed_companies:int,companies_with_anomalies:int,failed_companies:int,checked:int,anomalies:int,truncated:bool,wrapped:bool,duration_ms:int} */
    public function runBatch(int $companyLimit, int $itemLimit, int $perCompanySeconds, int $batchSeconds): array
    {
        $companyLimit = max(1, min(100, $companyLimit));
        $itemLimit = max(1, $itemLimit);
        $perCompanySeconds = max(1, $perCompanySeconds);
        $batchSeconds = max(30, min(300, $batchSeconds));
        $startedAt = hrtime(true);
        $deadline = microtime(true) + $batchSeconds;
        $cursor = max(0, (int) $this->cache->get(self::CURSOR_KEY, 0));
        $companyIds = $this->activeCompanyIdsAfter($cursor, $companyLimit);
        $wrapped = false;

        if ($companyIds === [] && $cursor > 0) {
            $wrapped = true;
            $this->cache->forever(self::CURSOR_KEY, 0);
            $companyIds = $this->activeCompanyIdsAfter(0, $companyLimit);
        }

        $processed = 0;
        $companiesWithAnomalies = 0;
        $failedCompanies = 0;
        $checked = 0;
        $anomalies = 0;
        $truncated = false;

        foreach ($companyIds as $companyId) {
            if (microtime(true) >= $deadline) {
                $truncated = true;
                break;
            }

            try {
                $remainingSeconds = max(1, (int) floor($deadline - microtime(true)));
                $result = $this->reconciliation->audit($companyId, $itemLimit, min($perCompanySeconds, $remainingSeconds));
                $checked += $result['checked'];
                $truncated = $truncated || $result['truncated'];
                $count = count($result['anomalies']);
                $anomalies += $count;
                $companiesWithAnomalies += $count > 0 ? 1 : 0;
            } catch (Throwable $exception) {
                $failedCompanies++;
                $this->logger->warning('Tenant document storage reconciliation failed.', [
                    'component' => 'document_storage_reconciliation',
                    'tenant_hash' => hash('sha256', (string) $companyId),
                    'exception_class' => $exception::class,
                ]);
            }

            $processed++;
            $this->cache->forever(self::CURSOR_KEY, $companyId);
        }

        return [
            'dry_run' => true,
            'processed_companies' => $processed,
            'companies_with_anomalies' => $companiesWithAnomalies,
            'failed_companies' => $failedCompanies,
            'checked' => $checked,
            'anomalies' => $anomalies,
            'truncated' => $truncated,
            'wrapped' => $wrapped,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }

    /** @return list<int> */
    private function activeCompanyIdsAfter(int $cursor, int $limit): array
    {
        return $this->db->table('companies')
            ->where('id', '>', $cursor)
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
