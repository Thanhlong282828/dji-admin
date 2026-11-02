<?php

namespace App\Support\Monitoring;

use App\Contracts\CloudMonitoringMetricsWriter;
use App\Support\ObservabilitySnapshotService;

final readonly class OperationsMetricExportService
{
    private const PREFIX = 'custom.googleapis.com/laraveldji/';

    public function __construct(
        private ObservabilitySnapshotService $observability,
        private CloudMonitoringMetricsWriter $exporter,
    ) {}

    public function export(): int
    {
        $snapshot = $this->observability->snapshot();
        $metrics = [
            $this->integer('http/request_count', $snapshot['http']['total']),
            $this->integer('http/5xx_count', $snapshot['http']['status_5xx']),
            $this->double('http/5xx_rate', $snapshot['http']['error_rate_percent'] / 100),
            $this->double('http/request_p95_ms', $snapshot['http']['p95_ms']),
            $this->integer('application/exception_count', $snapshot['exceptions']['total']),
            $this->integer('queue/failed_jobs', $snapshot['reliability']['queue']['failed']),
            $this->integer('queue/available', $snapshot['reliability']['queue']['available'] ? 1 : 0),
            $this->integer('scheduler/available', $snapshot['reliability']['scheduler']['age_seconds'] === null ? 0 : 1),
            $this->integer('application/readiness', $snapshot['reliability']['readiness']['status'] === 'ready' ? 1 : 0),
            $this->integer('exporter/success', 1),
        ];

        if ($snapshot['reliability']['queue']['available']) {
            $metrics[] = $this->integer('queue/depth', $snapshot['reliability']['queue']['pending']);
            if ($snapshot['reliability']['queue']['oldest_pending_seconds'] !== null) {
                $metrics[] = $this->integer('queue/oldest_pending_seconds', $snapshot['reliability']['queue']['oldest_pending_seconds']);
            }
        }
        if ($snapshot['reliability']['scheduler']['age_seconds'] !== null) {
            $metrics[] = $this->integer('scheduler/heartbeat_age_seconds', $snapshot['reliability']['scheduler']['age_seconds']);
        }

        $this->exporter->export($metrics);

        return count($metrics);
    }

    /** @return array{type:string,value:int,kind:'int64'} */
    private function integer(string $name, int $value): array
    {
        return ['type' => self::PREFIX.$name, 'value' => max(0, $value), 'kind' => 'int64'];
    }

    /** @return array{type:string,value:float,kind:'double'} */
    private function double(string $name, float $value): array
    {
        return ['type' => self::PREFIX.$name, 'value' => max(0.0, $value), 'kind' => 'double'];
    }
}
