<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;

final class HttpMetricsRecorder
{
    /** @var list<int> */
    private const LATENCY_BUCKETS_MS = [50, 100, 250, 500, 1000, 2500, 5000];

    public function __construct(private Repository $cache) {}

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
