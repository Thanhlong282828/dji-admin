<?php

namespace App\Support\Storage;

use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Throwable;

final class DocumentStorageTelemetry
{
    private const OPERATIONS = ['scanner', 'upload', 'download', 'head', 'signed_url', 'cleanup', 'cleanup_directory'];

    private const DISKS = ['local', 'supabase', 'metadata', 'clamav'];

    public function __construct(private Repository $cache, private LoggerInterface $logger) {}

    public function record(string $operation, string $disk, int $companyId, bool $success, float $durationMs, ?Throwable $exception = null, ?int $status = null): void
    {
        $operation = preg_replace('/[^a-z0-9_-]/i', '_', $operation) ?: 'unknown';
        $disk = preg_replace('/[^a-z0-9_-]/i', '_', $disk) ?: 'unknown';
        $classification = $this->classification($exception, $status);
        $context = [
            'component' => 'document_storage',
            'operation' => $operation,
            'disk' => $disk,
            'tenant_hash' => hash('sha256', (string) $companyId),
            'outcome' => $success ? 'success' : 'failure',
            'error_classification' => $classification,
            'duration_ms' => round(max(0, $durationMs), 2),
        ];

        try {
            $configuredTtl = config('operations.metrics.ttl_seconds', 3600);
            $ttl = is_numeric($configuredTtl) ? max(60, (int) $configuredTtl) : 3600;
            $prefix = "operations:storage:{$operation}:{$disk}";
            $this->increment("{$prefix}:total", $ttl);
            $this->increment("{$prefix}:".($success ? 'success' : 'failure'), $ttl);
            $this->incrementBy("{$prefix}:duration_ms", (int) ceil(max(0, $durationMs)), $ttl);
            if (! $success) {
                $this->increment("operations:storage:errors:{$classification}", $ttl);
            }
            $this->logger->log($success ? 'info' : 'warning', 'Document storage operation completed.', $context + ($exception === null ? [] : ['exception_class' => $exception::class]));
        } catch (Throwable $telemetryFailure) {
            try {
                $this->logger->warning('Document storage telemetry failed.', ['component' => 'document_storage', 'operation' => $operation, 'exception_class' => $telemetryFailure::class]);
            } catch (Throwable) {
                // Telemetry must never replace the storage result.
            }
        }
    }

    public function incrementAnomaly(string $type, int $companyId): void
    {
        $this->record('reconciliation_'.$type, 'metadata', $companyId, false, 0, null, null);
    }

    /** @return array{operations:array<string,array{total:int,success:int,failure:int,duration_ms:int}>,failures:int,scanner_failures:int,cleanup_failures:int,signed_url_failures:int,reconciliation_anomalies:int} */
    public function snapshot(): array
    {
        $operations = [];
        $failures = 0;
        foreach (self::OPERATIONS as $operation) {
            $totals = ['total' => 0, 'success' => 0, 'failure' => 0, 'duration_ms' => 0];
            foreach (self::DISKS as $disk) {
                $prefix = "operations:storage:{$operation}:{$disk}";
                foreach (array_keys($totals) as $metric) {
                    $totals[$metric] += $this->cacheInteger("{$prefix}:{$metric}");
                }
            }
            $operations[$operation] = $totals;
            $failures += $totals['failure'];
        }

        return [
            'operations' => $operations,
            'failures' => $failures,
            'scanner_failures' => $operations['scanner']['failure'],
            'cleanup_failures' => $operations['cleanup']['failure'] + $operations['cleanup_directory']['failure'],
            'signed_url_failures' => $operations['signed_url']['failure'],
            'reconciliation_anomalies' => $this->reconciliationAnomalies(),
        ];
    }

    private function classification(?Throwable $exception, ?int $status): string
    {
        if ($status !== null) {
            return match (true) {
                $status === 403 => 'http_403',
                $status === 404 => 'http_404',
                $status === 415 => 'http_415',
                $status === 429 => 'http_429',
                $status >= 500 => 'http_5xx',
                default => 'http_'.$status,
            };
        }

        if ($exception === null) {
            return 'none';
        }

        $name = strtolower($exception::class.' '.$exception->getMessage());

        return match (true) {
            str_contains($name, 'timeout') => 'timeout',
            str_contains($name, 'accessdenied') || str_contains($name, 'forbidden') => 'access_denied',
            str_contains($name, 'notfound') => 'not_found',
            str_contains($name, 'ratelimit') || str_contains($name, 'too many') => 'rate_limited',
            default => 'transport_error',
        };
    }

    private function increment(string $key, int $ttl): void
    {
        $this->incrementBy($key, 1, $ttl);
    }

    private function incrementBy(string $key, int $amount, int $ttl): void
    {
        $this->cache->add($key, 0, $ttl);
        $this->cache->increment($key, $amount);
    }

    private function cacheInteger(string $key): int
    {
        $value = $this->cache->get($key, 0);

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    private function reconciliationAnomalies(): int
    {
        $total = 0;
        foreach (['missing_object', 'orphan_object', 'size_mismatch', 'checksum_mismatch', 'wrong_provider_reference', 'duplicate_reference', 'provider_scan_failed'] as $type) {
            $total += $this->cacheInteger("operations:storage:reconciliation_{$type}:metadata:failure");
        }

        return $total;
    }
}
