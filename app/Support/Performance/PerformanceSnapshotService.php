<?php

namespace App\Support\Performance;

use App\Support\ObservabilitySnapshotService;
use Illuminate\Database\ConnectionInterface;

final readonly class PerformanceSnapshotService
{
    public function __construct(private ConnectionInterface $db, private ObservabilitySnapshotService $observability) {}

    /** @return array{schema_version:int,generated_at:string,checksum:string,http:array{total:int,p50_ms:float,p95_ms:float,p99_ms:float},database:array{tables:int,indexes:int,estimated_rows:int},cache:array{strategy:string,scope:string,invalidation:string},exports:array{employee:string,payroll:string,audit:string},queue:array{pending:int,failed:int},findings:list<array{key:string,severity:string,component:string,category:string,description:string,evidence:string,recommendation:string,status:string}>,critical_findings:int} */
    public function snapshot(): array
    {
        $observability = $this->observability->snapshot();
        $database = $this->databaseInventory();
        $findings = array_map(static fn (PerformanceFinding $finding): array => $finding->toArray(), $this->findings());
        $payload = [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'http' => ['total' => $observability['http']['total'], 'p50_ms' => $observability['http']['p50_ms'], 'p95_ms' => $observability['http']['p95_ms'], 'p99_ms' => $observability['http']['p99_ms']],
            'database' => $database,
            'cache' => ['strategy' => 'versioned typed arrays/DTO', 'scope' => 'company + version + normalized filters', 'invalidation' => 'domain event/version bump + bounded TTL'],
            'exports' => ['employee' => 'lazyById(500) with batched eager loading', 'payroll' => 'cursor streaming + formula sanitization', 'audit' => 'cursor streaming + immutable checksum'],
            'queue' => ['pending' => $observability['reliability']['queue']['pending'], 'failed' => $observability['reliability']['queue']['failed']],
            'findings' => $findings,
            'critical_findings' => count(array_filter($findings, static fn (array $finding): bool => $finding['severity'] === 'critical' && $finding['status'] === 'open')),
        ];

        return ['checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), ...$payload];
    }

    /** @return list<PerformanceFinding> */
    public function findings(): array
    {
        return [
            new PerformanceFinding('employee-export-relations', 'warning', 'Employee', 'QUERY', 'Per-row relation loading in large employee exports.', 'cursor() plus relationship access was an N+1 candidate.', 'Use lazyById windows with explicit relations.', 'resolved'),
            new PerformanceFinding('iam-list-window', 'warning', 'IAM', 'MEMORY', 'Several governance lists use fixed result limits instead of pagination.', 'Requests/history are bounded at 100/200 rows.', 'Adopt cursor pagination when operational volume reaches the documented threshold.', 'accepted'),
            new PerformanceFinding('employee-list-columns', 'info', 'Employee', 'QUERY', 'List reads should not hydrate restricted identity payload.', 'Employee list now selects only display and relationship keys.', 'Keep detail-only restricted fields out of list projections.', 'resolved'),
            new PerformanceFinding('streaming-exports', 'info', 'Export', 'EXPORT', 'Large exports require bounded iteration.', 'Employee, Payroll, Organization and Audit use cursor/chunk strategies.', 'Retain query-count and row-checksum regressions.', 'resolved'),
        ];
    }

    /** @return array{tables:int,indexes:int,estimated_rows:int} */
    private function databaseInventory(): array
    {
        $schema = $this->db->getDatabaseName();
        $tables = (int) $this->db->table('information_schema.tables')->where('table_schema', $schema)->count();
        $indexes = (int) $this->db->table('information_schema.statistics')->where('table_schema', $schema)->count();
        $estimatedRows = (int) $this->db->table('information_schema.tables')->where('table_schema', $schema)->sum('table_rows');

        return ['tables' => $tables, 'indexes' => $indexes, 'estimated_rows' => max(0, $estimatedRows)];
    }
}
