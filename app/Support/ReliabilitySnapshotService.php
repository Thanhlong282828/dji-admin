<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ReliabilitySnapshotService
{
    /** @var array<string, string> */
    private const OUTBOXES = [
        'workflow' => 'workflow_outbox',
        'notification' => 'notification_outbox',
        'iam' => 'iam_outbox',
        'integration' => 'integration_outbox',
        'audit' => 'audit_outbox',
    ];

    public function __construct(private ConnectionInterface $db, private OperationsHealthService $health, private SloCalculator $slo) {}

    /** @return array{readiness:array{status:'ready'|'degraded',checks:array{database:bool,redis:bool,queue:bool},timestamp:string},queue:array{pending:int,failed:int},outboxes:array<string,array{pending:int,failed:int,oldest_pending_seconds:int|null}>,integration:array{failed:int,dead_letters:int},scheduler:array{status:string,age_seconds:int|null},slo:array{window_minutes:int,total:int,failures:int,allowed_failures:int,consumed_percent:float,remaining_percent:float}} */
    public function snapshot(): array
    {
        $readiness = $this->health->readiness();
        $queuePending = $this->count('jobs');
        $failedJobs = $this->count('failed_jobs');
        $outboxes = [];
        foreach (self::OUTBOXES as $name => $table) {
            $outboxes[$name] = $this->outbox($table);
        }

        $heartbeat = Cache::get('operations:scheduler:heartbeat');
        $age = is_string($heartbeat) ? max(0, (int) round(CarbonImmutable::parse($heartbeat)->diffInSeconds(now(), true))) : null;
        $requestTotal = $this->nonNegativeInt(Cache::get('operations:http:total', 0));
        $requestFailures = $this->nonNegativeInt(Cache::get('operations:http:5xx', 0));
        $staleSeconds = $this->positiveInt(config('operations.scheduler_stale_seconds'), 180);
        $sloTarget = $this->floatValue(config('operations.slo.http_availability_target'), 99.9);
        $sloWindow = $this->positiveInt(config('operations.slo.window_minutes'), 43_200);

        return [
            'readiness' => $readiness,
            'queue' => ['pending' => $queuePending, 'failed' => $failedJobs],
            'outboxes' => $outboxes,
            'integration' => [
                'failed' => $this->countWhere('integration_outbound_messages', 'status', 'failed'),
                'dead_letters' => $this->countWhere('integration_dead_letters', 'status', 'open'),
            ],
            'scheduler' => ['status' => $age !== null && $age <= $staleSeconds ? 'healthy' : 'stale', 'age_seconds' => $age],
            'slo' => $this->slo->calculate($requestTotal, $requestFailures, $sloTarget, $sloWindow),
        ];
    }

    /** @return array{pending:int,failed:int,oldest_pending_seconds:int|null} */
    private function outbox(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return ['pending' => 0, 'failed' => 0, 'oldest_pending_seconds' => null];
        }
        try {
            $columns = Schema::getColumnListing($table);
            $published = in_array('published_at', $columns, true) ? 'published_at' : (in_array('processed_at', $columns, true) ? 'processed_at' : null);
            $query = $this->db->table($table);
            if ($published !== null) {
                $query->whereNull($published);
            }
            $pending = (int) $query->count();
            $oldest = in_array('created_at', $columns, true) ? $query->min('created_at') : null;
            $age = is_string($oldest) ? max(0, (int) round(CarbonImmutable::parse($oldest)->diffInSeconds(now(), true))) : null;
            $failed = in_array('status', $columns, true) ? (int) $this->db->table($table)->where('status', 'failed')->count() : 0;

            return ['pending' => $pending, 'failed' => $failed, 'oldest_pending_seconds' => $age];
        } catch (Throwable) {
            return ['pending' => 0, 'failed' => 0, 'oldest_pending_seconds' => null];
        }
    }

    private function count(string $table): int
    {
        try {
            return Schema::hasTable($table) ? (int) $this->db->table($table)->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private function countWhere(string $table, string $column, string $value): int
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumn($table, $column) ? (int) $this->db->table($table)->where($column, $value)->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private function nonNegativeInt(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    private function positiveInt(mixed $value, int $fallback): int
    {
        return is_numeric($value) ? max(1, (int) $value) : $fallback;
    }

    private function floatValue(mixed $value, float $fallback): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }
}
