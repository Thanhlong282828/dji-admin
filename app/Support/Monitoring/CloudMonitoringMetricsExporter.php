<?php

namespace App\Support\Monitoring;

use App\Contracts\CloudMonitoringMetricsWriter;
use Illuminate\Http\Client\Factory;
use RuntimeException;
use Throwable;

final readonly class CloudMonitoringMetricsExporter implements CloudMonitoringMetricsWriter
{
    public function __construct(private Factory $http, private GceMonitoringMetadataProvider $metadata) {}

    /** @param list<array{type:string,value:int|float,kind:'int64'|'double'}> $metrics */
    public function export(array $metrics): void
    {
        if ($metrics === []) {
            throw new RuntimeException('Cloud Monitoring export requires at least one metric.');
        }
        if (count($metrics) > 20) {
            throw new RuntimeException('Cloud Monitoring export batch exceeds the bounded metric limit.');
        }
        foreach ($metrics as $metric) {
            if (preg_match('#^custom\.googleapis\.com/laraveldji/[a-z0-9_/]+$#', $metric['type']) !== 1) {
                throw new RuntimeException('Cloud Monitoring metric type is outside the canonical namespace.');
            }
        }

        $identity = $this->metadata->credentials();
        $endTime = now()->toRfc3339String();
        $timeSeries = [];
        foreach ($metrics as $metric) {
            $typedValue = $metric['kind'] === 'int64'
                ? ['int64Value' => (string) max(0, (int) $metric['value'])]
                : ['doubleValue' => max(0.0, (float) $metric['value'])];
            $timeSeries[] = [
                'metric' => ['type' => $metric['type']],
                'resource' => [
                    'type' => 'gce_instance',
                    'labels' => [
                        'project_id' => $identity['project_id'],
                        'instance_id' => $identity['instance_id'],
                        'zone' => $identity['zone'],
                    ],
                ],
                'points' => [['interval' => ['endTime' => $endTime], 'value' => $typedValue]],
            ];
        }

        try {
            $response = $this->http->withToken($identity['access_token'])
                ->acceptJson()
                ->asJson()
                ->connectTimeout(2)
                ->timeout($this->timeout())
                ->post('https://monitoring.googleapis.com/v3/projects/'.rawurlencode($identity['project_id']).'/timeSeries', [
                    'timeSeries' => $timeSeries,
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Cloud Monitoring metric export transport failed.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Cloud Monitoring metric export failed with HTTP '.$response->status().'.');
        }
    }

    private function timeout(): int
    {
        $value = config('operations.metrics_export.timeout_seconds');

        return is_numeric($value) ? min(10, max(1, (int) $value)) : 5;
    }
}
