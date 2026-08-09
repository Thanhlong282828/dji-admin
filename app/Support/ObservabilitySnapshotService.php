<?php

namespace App\Support;

final class ObservabilitySnapshotService
{
    public function __construct(
        private ReliabilitySnapshotService $reliability,
        private HttpMetricsRecorder $httpMetrics,
        private HistogramPercentileCalculator $percentiles,
        private AlertEvaluator $alerts,
    ) {}

    /** @return array{reliability:array{readiness:array{status:'ready'|'degraded',checks:array{database:bool,redis:bool,queue:bool},timestamp:string},queue:array{pending:int,failed:int},outboxes:array<string,array{pending:int,failed:int,oldest_pending_seconds:int|null}>,integration:array{failed:int,dead_letters:int},scheduler:array{status:string,age_seconds:int|null},slo:array{window_minutes:int,total:int,failures:int,allowed_failures:int,consumed_percent:float,remaining_percent:float}},http:array{total:int,status_5xx:int,error_rate_percent:float,average_ms:float,p50_ms:float,p95_ms:float,p99_ms:float},burn_rate:array{short:float,long:float,status:string},alerts:list<array{name:string,component:string,signal:string,value:float,threshold:float,severity:string,runbook:string,window_minutes:int}>,generated_at:string} */
    public function snapshot(): array
    {
        $reliability = $this->reliability->snapshot();
        $histogram = $this->httpMetrics->snapshot();
        $total = $reliability['slo']['total'];
        $failures = $reliability['slo']['failures'];
        $errorRate = $total > 0 ? ($failures / $total) * 100 : 0.0;
        $target = $this->floatConfig('operations.slo.http_availability_target', 99.9);
        $budgetRate = max(0.0001, 100.0 - $target);
        $burn = $errorRate / $budgetRate;

        $http = [
            'total' => $total,
            'status_5xx' => $failures,
            'error_rate_percent' => round($errorRate, 4),
            'average_ms' => $histogram['count'] > 0 ? round($histogram['sum_ms'] / $histogram['count'], 2) : 0.0,
            'p50_ms' => $this->percentiles->calculate($histogram['buckets'], $histogram['count'], 50),
            'p95_ms' => $this->percentiles->calculate($histogram['buckets'], $histogram['count'], 95),
            'p99_ms' => $this->percentiles->calculate($histogram['buckets'], $histogram['count'], 99),
        ];
        $signals = [
            'http_5xx_rate_percent' => $http['error_rate_percent'],
            'http_p95_ms' => $http['p95_ms'],
            'queue_depth' => $reliability['queue']['pending'],
            'failed_jobs' => $reliability['queue']['failed'],
            'scheduler_age_seconds' => $reliability['scheduler']['age_seconds'] ?? PHP_INT_MAX,
            'integration_dead_letters' => $reliability['integration']['dead_letters'],
            'notification_failures' => $reliability['outboxes']['notification']['failed'] ?? 0,
            'outbox_oldest_pending_seconds' => $this->oldestOutboxAge($reliability['outboxes']),
            'database_ready' => $reliability['readiness']['checks']['database'] ? 1 : 0,
            'redis_ready' => $reliability['readiness']['checks']['redis'] ? 1 : 0,
        ];

        return [
            'reliability' => $reliability,
            'http' => $http,
            'burn_rate' => ['short' => round($burn, 2), 'long' => round($burn, 2), 'status' => $burn >= 2 ? 'critical' : ($burn >= 1 ? 'warning' : 'healthy')],
            'alerts' => $this->alerts->evaluate($signals, $this->alertDefinitions()),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, array{component:string,signal:string,operator:string,threshold:int|float,severity:string,runbook:string,window_minutes:int}> */
    private function alertDefinitions(): array
    {
        $definitions = config('operations.alerts');
        if (! is_array($definitions)) {
            return [];
        }

        $normalized = [];
        foreach ($definitions as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                continue;
            }
            $component = $definition['component'] ?? null;
            $signal = $definition['signal'] ?? null;
            $operator = $definition['operator'] ?? null;
            $threshold = $definition['threshold'] ?? null;
            $severity = $definition['severity'] ?? null;
            $runbook = $definition['runbook'] ?? null;
            $window = $definition['window_minutes'] ?? null;
            if (! is_string($component) || ! is_string($signal) || ! is_string($operator) || ! is_numeric($threshold) || ! is_string($severity) || ! is_string($runbook) || ! is_numeric($window)) {
                continue;
            }
            $normalized[$name] = ['component' => $component, 'signal' => $signal, 'operator' => $operator, 'threshold' => (float) $threshold, 'severity' => $severity, 'runbook' => $runbook, 'window_minutes' => max(1, (int) $window)];
        }

        return $normalized;
    }

    private function floatConfig(string $key, float $fallback): float
    {
        $value = config($key);

        return is_numeric($value) ? (float) $value : $fallback;
    }

    /** @param array<string, array{pending:int,failed:int,oldest_pending_seconds:int|null}> $outboxes */
    private function oldestOutboxAge(array $outboxes): int
    {
        $oldest = 0;
        foreach ($outboxes as $outbox) {
            $oldest = max($oldest, $outbox['oldest_pending_seconds'] ?? 0);
        }

        return $oldest;
    }
}
