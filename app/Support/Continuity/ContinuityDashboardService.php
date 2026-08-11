<?php

namespace App\Support\Continuity;

final class ContinuityDashboardService
{
    public function __construct(private DrCertificateReader $reader, private ServiceContinuityCatalog $catalog, private BackupInventoryService $inventory) {}

    /** @return array{certificate:array{available:bool,integrity:bool,status:string,exercise_id:string,completed_at:string,scope:string,checksum:string,gate_results:array<string,bool|int|float|string>,rto:array<string,bool|int|float|string>,rpo:array<string,bool|int|float|string>},services:list<array{component:string,criticality:string,impact:string,dependencies:list<string>,degraded_behavior:string,recovery_prerequisite:string}>,inventory:list<array{asset:string,classification:string,reason:string}>,runbook_count:int} */
    public function snapshot(): array
    {
        $runbooks = glob(base_path('docs/runbooks/*.md'));

        return ['certificate' => $this->reader->latest(), 'services' => $this->catalog->entries(), 'inventory' => $this->inventory->entries(), 'runbook_count' => is_array($runbooks) ? count($runbooks) : 0];
    }
}
