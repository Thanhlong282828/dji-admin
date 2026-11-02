<?php

namespace App\Support\Monitoring;

use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ApplicationExceptionMetricsRecorder
{
    public function __construct(private Repository $cache, private LoggerInterface $logger) {}

    public function recordSafely(): bool
    {
        try {
            $ttl = config('operations.metrics.ttl_seconds');
            $seconds = is_numeric($ttl) ? max(60, (int) $ttl) : 3600;
            $this->cache->add('operations:application:exceptions', 0, $seconds);
            $this->cache->increment('operations:application:exceptions');

            return true;
        } catch (Throwable $exception) {
            try {
                $this->logger->warning('Application exception metric recording failed.', [
                    'component' => 'application_exception_metrics',
                    'exception_class' => $exception::class,
                ]);
            } catch (Throwable) {
                // Telemetry failure must not replace the original exception.
            }

            return false;
        }
    }
}
