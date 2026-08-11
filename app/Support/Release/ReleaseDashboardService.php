<?php

namespace App\Support\Release;

use App\Support\OperationsHealthService;
use Illuminate\Database\ConnectionInterface;

final class ReleaseDashboardService
{
    public function __construct(private ReleaseManifestReader $reader, private ReleaseCertificateReader $certificates, private OperationsHealthService $health, private ConnectionInterface $db) {}

    /** @return array{release:array{available:bool,integrity:bool,release_id:string,git_commit:string,git_tag:string,source_state:string,source_checksum:string,image_reference:string,image_digest:string,environment:string,migration_count:int,runtime_config_schema_version:int,runtime_config_checksum:string,runtime_config_drift_status:string,quality_gates:array<string,string>,rollback_target:string,created_at:string},certificate:array{available:bool,integrity:bool,status:string,release_id:string,git_commit:string,image_digest:string,completed_at:string,rollback_target:string,rollback_compatible:bool,gate_results:array<string,bool|int|float|string>,health_results:array<string,bool|int|float|string>},health:array{status:'ready'|'degraded',checks:array{database:bool,redis:bool,queue:bool},timestamp:string},migrationCount:int} */
    public function snapshot(): array
    {
        return ['release' => $this->reader->current(), 'certificate' => $this->certificates->latest(), 'health' => $this->health->readiness(), 'migrationCount' => (int) $this->db->table('migrations')->count()];
    }
}
