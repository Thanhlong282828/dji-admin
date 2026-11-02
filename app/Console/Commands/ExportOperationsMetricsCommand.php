<?php

namespace App\Console\Commands;

use App\Support\Monitoring\OperationsMetricExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use Throwable;

final class ExportOperationsMetricsCommand extends Command
{
    protected $signature = 'operations:export-metrics';

    protected $description = 'Export a bounded application metrics batch to Google Cloud Monitoring';

    public function handle(OperationsMetricExportService $exporter, LoggerInterface $logger): int
    {
        if (! config('operations.metrics_export.enabled')) {
            $this->components->info('Cloud Monitoring application metric export is disabled.');

            return self::SUCCESS;
        }

        try {
            $count = $exporter->export();
            Cache::put('operations:metrics-export:last-success-at', now()->toIso8601String(), now()->addDay());
            $this->components->info("Exported {$count} bounded application metrics.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Cache::put('operations:metrics-export:last-failure-at', now()->toIso8601String(), now()->addDay());
            $logger->warning('Cloud Monitoring application metric export failed.', [
                'component' => 'cloud_monitoring_metrics_export',
                'exception_class' => $exception::class,
            ]);
            $this->components->error('Cloud Monitoring application metric export failed safely.');

            return self::FAILURE;
        }
    }
}
