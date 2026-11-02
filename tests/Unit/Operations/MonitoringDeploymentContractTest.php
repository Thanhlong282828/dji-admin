<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;

final class MonitoringDeploymentContractTest extends TestCase
{
    public function test_metric_descriptors_are_canonical_bounded_and_label_free(): void
    {
        $document = $this->json('deploy/monitoring/gcp/laraveldji-metric-descriptors.json');
        $descriptors = $document['descriptors'] ?? null;
        self::assertIsArray($descriptors);
        self::assertCount(13, $descriptors);

        foreach ($descriptors as $descriptor) {
            self::assertIsArray($descriptor);
            $type = $descriptor['type'] ?? null;
            self::assertIsString($type);
            self::assertMatchesRegularExpression('#^custom\.googleapis\.com/laraveldji/[a-z0-9_/]+$#', $type);
            self::assertSame('GAUGE', $descriptor['metricKind'] ?? null);
            self::assertContains($descriptor['valueType'] ?? null, ['INT64', 'DOUBLE']);
            self::assertArrayNotHasKey('labels', $descriptor);
        }
    }

    public function test_alert_policy_thresholds_windows_missing_data_and_channel_are_explicit(): void
    {
        $document = $this->json('deploy/monitoring/gcp/laraveldji-alert-policies.json');
        self::assertSame('__NOTIFICATION_CHANNEL_RESOURCE__', $document['notificationChannelPlaceholder'] ?? null);
        $policies = $document['policies'] ?? null;
        self::assertIsArray($policies);
        self::assertCount(10, $policies);

        $expected = [
            'LaravelDji - Queue depth high' => [100.0, '600s'],
            'LaravelDji - Oldest queued job stale' => [300.0, '600s'],
            'LaravelDji - Failed jobs present' => [1.0, '600s'],
            'LaravelDji - HTTP 5xx rate high' => [0.05, '300s'],
            'LaravelDji - HTTP p95 high' => [1500.0, '300s'],
            'LaravelDji - Scheduler stale' => [180.0, '300s'],
            'LaravelDji - VM CPU high' => [0.7, '300s'],
            'LaravelDji - VM RAM high' => [75.0, '300s'],
            'LaravelDji - VM disk high' => [70.0, '300s'],
        ];
        foreach ($policies as $policy) {
            self::assertIsArray($policy);
            self::assertSame(['__NOTIFICATION_CHANNEL_RESOURCE__'], $policy['notificationChannels']);
            $displayName = $policy['displayName'] ?? null;
            self::assertIsString($displayName);
            $conditions = $policy['conditions'] ?? null;
            self::assertIsArray($conditions);
            $condition = $conditions[0] ?? null;
            self::assertIsArray($condition);
            if ($displayName === 'LaravelDji - Metrics exporter absent') {
                $conditionAbsent = $condition['conditionAbsent'] ?? null;
                self::assertIsArray($conditionAbsent);
                self::assertSame('300s', $conditionAbsent['duration'] ?? null);

                continue;
            }
            self::assertArrayHasKey($displayName, $expected);
            [$threshold, $duration] = $expected[$displayName];
            $conditionThreshold = $condition['conditionThreshold'] ?? null;
            self::assertIsArray($conditionThreshold);
            $thresholdValue = $conditionThreshold['thresholdValue'] ?? null;
            self::assertIsNumeric($thresholdValue);
            self::assertSame($threshold, (float) $thresholdValue);
            self::assertSame($duration, $conditionThreshold['duration'] ?? null);
            self::assertSame('EVALUATION_MISSING_DATA_ACTIVE', $conditionThreshold['evaluationMissingData'] ?? null);
        }
    }

    public function test_dashboard_uses_only_governed_infrastructure_and_custom_metric_namespaces(): void
    {
        $dashboard = file_get_contents(dirname(__DIR__, 3).'/deploy/monitoring/gcp/laraveldji-unified-dashboard.json');
        self::assertIsString($dashboard);
        foreach (['agent.googleapis.com/', 'compute.googleapis.com/', 'cloudsql.googleapis.com/', 'custom.googleapis.com/laraveldji/'] as $namespace) {
            self::assertStringContainsString($namespace, $dashboard);
        }
        foreach (['http/5xx_rate', 'http/request_p95_ms', 'application/exception_count', 'application/readiness', 'queue/depth', 'queue/oldest_pending_seconds', 'queue/failed_jobs', 'scheduler/heartbeat_age_seconds', 'exporter/success'] as $metric) {
            self::assertStringContainsString($metric, $dashboard);
        }
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        $contents = file_get_contents(dirname(__DIR__, 3).'/'.$path);
        self::assertIsString($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
