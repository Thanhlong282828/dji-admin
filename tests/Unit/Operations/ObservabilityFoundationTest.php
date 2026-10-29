<?php

namespace Tests\Unit\Operations;

use App\Support\AlertEvaluator;
use App\Support\HistogramPercentileCalculator;
use App\Support\PercentileCalculator;
use App\Support\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

final class ObservabilityFoundationTest extends TestCase
{
    public function test_percentile_calculators_are_deterministic(): void
    {
        self::assertSame(40.0, (new PercentileCalculator)->calculate([10, 50, 20, 40, 30], 75));
        self::assertSame(250.0, (new HistogramPercentileCalculator)->calculate([50 => 2, 100 => 4, 250 => 10], 10, 95));
        self::assertSame(0.0, (new HistogramPercentileCalculator)->calculate([], 0, 95));
    }

    public function test_alert_evaluation_uses_configured_operator_threshold_and_runbook(): void
    {
        $alerts = (new AlertEvaluator)->evaluate(
            ['queue_depth' => 101],
            ['queue_backlog' => ['component' => 'queue', 'signal' => 'queue_depth', 'operator' => 'gte', 'threshold' => 100, 'severity' => 'warning', 'runbook' => 'queue-worker-down.md', 'window_minutes' => 10]],
        );

        self::assertCount(1, $alerts);
        self::assertSame('queue_backlog', $alerts[0]['name']);
        self::assertSame('queue-worker-down.md', $alerts[0]['runbook']);
    }

    public function test_oldest_queue_job_alert_has_sustained_window_and_recovery_boundary(): void
    {
        $definition = ['component' => 'queue', 'signal' => 'queue_oldest_pending_seconds', 'operator' => 'gte', 'threshold' => 300, 'severity' => 'critical', 'runbook' => 'docs/runbooks/queue-backlog-recovery.md', 'window_minutes' => 10];
        self::assertSame('queue_oldest_pending_seconds', $definition['signal']);
        self::assertGreaterThanOrEqual(5, $definition['window_minutes']);

        $triggered = (new AlertEvaluator)->evaluate(['queue_oldest_pending_seconds' => 301], ['queue_oldest_job' => $definition]);
        $recovered = (new AlertEvaluator)->evaluate(['queue_oldest_pending_seconds' => 0], ['queue_oldest_job' => $definition]);
        self::assertCount(1, $triggered);
        self::assertSame([], $recovered);
    }

    public function test_sensitive_data_is_redacted_recursively(): void
    {
        $safe = (new SensitiveDataRedactor)->redact([
            'component' => 'integration',
            'authorization' => 'Bearer secret',
            'nested' => ['salary' => 5000, 'status' => 'failed'],
        ]);

        self::assertSame('[REDACTED]', $safe['authorization']);
        $nested = $safe['nested'];
        self::assertIsArray($nested);
        self::assertSame('[REDACTED]', $nested['salary']);
        self::assertSame('failed', $nested['status']);
    }
}
