<?php

namespace App\Support\Release;

use App\Support\OperationsHealthService;
use Illuminate\Database\ConnectionInterface;

final class ReleaseDashboardService
{
    public function __construct(private ReleaseManifestReader $reader, private OperationsHealthService $health, private ConnectionInterface $db) {}

    /** @return array{release:array{available:bool,integrity:bool,release_id:string,git_commit:string,git_tag:string,image_reference:string,image_digest:string,environment:string,migration_count:int,quality_gates:array<string,string>,rollback_target:string,created_at:string},health:array{status:'ready'|'degraded',checks:array{database:bool,redis:bool,queue:bool},timestamp:string},migrationCount:int} */
    public function snapshot(): array
    {
        return ['release' => $this->reader->current(), 'health' => $this->health->readiness(), 'migrationCount' => (int) $this->db->table('migrations')->count()];
    }
}
