<?php

namespace Tests\Unit\Operations;

use App\Support\Monitoring\CloudMonitoringMetricsExporter;
use App\Support\Monitoring\GceMonitoringMetadataProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class CloudMonitoringMetricsExporterTest extends TestCase
{
    public function test_export_uses_authenticated_gce_resource_and_bounded_canonical_metrics(): void
    {
        Http::fake([
            'http://metadata.google.internal/computeMetadata/v1/project/project-id' => Http::response('project-safe', 200, ['Metadata-Flavor' => 'Google']),
            'http://metadata.google.internal/computeMetadata/v1/instance/id' => Http::response('123456789', 200, ['Metadata-Flavor' => 'Google']),
            'http://metadata.google.internal/computeMetadata/v1/instance/zone' => Http::response('projects/1/zones/asia-southeast1-a', 200, ['Metadata-Flavor' => 'Google']),
            'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token' => Http::response(['access_token' => 'temporary-test-token', 'expires_in' => 300, 'token_type' => 'Bearer'], 200, ['Metadata-Flavor' => 'Google']),
            'https://monitoring.googleapis.com/v3/projects/project-safe/timeSeries' => Http::response([], 200),
        ]);

        $exporter = app(CloudMonitoringMetricsExporter::class);
        $exporter->export([
            ['type' => 'custom.googleapis.com/laraveldji/http/5xx_rate', 'value' => 0.05, 'kind' => 'double'],
            ['type' => 'custom.googleapis.com/laraveldji/queue/depth', 'value' => 101, 'kind' => 'int64'],
        ]);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://monitoring.googleapis.com/v3/projects/project-safe/timeSeries') {
                return true;
            }
            $series = $request->data()['timeSeries'] ?? null;
            self::assertIsArray($series);
            self::assertCount(2, $series);
            $first = $series[0] ?? null;
            self::assertIsArray($first);
            $resource = $first['resource'] ?? null;
            self::assertIsArray($resource);
            $labels = $resource['labels'] ?? null;
            self::assertIsArray($labels);
            $metric = $first['metric'] ?? null;
            self::assertIsArray($metric);
            self::assertSame('gce_instance', $resource['type'] ?? null);
            self::assertSame('123456789', $labels['instance_id'] ?? null);
            self::assertArrayNotHasKey('labels', $metric);
            self::assertStringNotContainsString('temporary-test-token', $request->body());

            return true;
        });
    }

    public function test_metadata_authenticity_failure_is_fail_closed(): void
    {
        Http::fake([
            'http://metadata.google.internal/*' => Http::response('not metadata', 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('authenticity');

        app(GceMonitoringMetadataProvider::class)->credentials();
    }

    public function test_foreign_metric_namespace_is_rejected_before_remote_write(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('canonical namespace');

        app(CloudMonitoringMetricsExporter::class)->export([
            ['type' => 'custom.googleapis.com/foreign/secret', 'value' => 1, 'kind' => 'int64'],
        ]);
    }
}
