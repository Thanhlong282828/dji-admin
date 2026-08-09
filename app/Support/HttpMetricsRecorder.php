<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Throwable;

final class HttpMetricsRecorder
{
    /** @var list<int> */
    private const LATENCY_BUCKETS_MS = [50, 100, 250, 500, 1000, 2500, 5000];

    public function __construct(private Repository $cache, private LoggerInterface $logger) {}

    /**
     * Metrics are a non-critical side effect. This boundary deliberately catches
     * every telemetry failure so it can never replace an application response.
     */
    public function recordSafely(int $status, float $durationMilliseconds): bool
    {
        try {
            $this->record($status, $durationMilliseconds);

            return true;
        } catch (Throwable $exception) {
            try {
                $this->logger->warning('HTTP metrics recording failed.', [
                    'component' => 'http_metrics',
                    'exception_class' => $exception::class,
                ]);
            } catch (Throwable $loggingFailure) {
                error_log('HTTP metrics recording failed; fallback logger failure: '.$loggingFailure::class);
            }

            return false;
        }
    }

    public function record(int $status, float $durationMilliseconds): void
    {
        $ttl = $this->ttl();
        $this->increment('operations:http:total', $ttl);
        $this->increment('operations:http:status:'.intdiv(max(100, $status), 100).'xx', $ttl);

        if ($status >= 500) {
            $this->increment('operations:http:5xx', $ttl);
        }

        $duration = max(0, (int) ceil($durationMilliseconds));
        foreach (self::LATENCY_BUCKETS_MS as $boundary) {
            if ($duration <= $boundary) {
                $this->increment('operations:http:latency:le:'.$boundary, $ttl);
            }
        }
        $this->increment('operations:http:latency:count', $ttl);
        $this->incrementBy('operations:http:latency:sum_ms', $duration, $ttl);
    }

    /** @return array{count:int,sum_ms:int,buckets:array<int,int>} */
    public function snapshot(): array
    {
        $buckets = [];
        foreach (self::LATENCY_BUCKETS_MS as $boundary) {
            $buckets[$boundary] = $this->integer($this->cache->get('operations:http:latency:le:'.$boundary));
        }

        return [
            'count' => $this->integer($this->cache->get('operations:http:latency:count')),
            'sum_ms' => $this->integer($this->cache->get('operations:http:latency:sum_ms')),
            'buckets' => $buckets,
        ];
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

    private function ttl(): int
    {
        $value = config('operations.metrics.ttl_seconds');

        return is_numeric($value) ? max(60, (int) $value) : 3600;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
