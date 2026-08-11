<?php

namespace App\Support\Continuity;

final class ServiceContinuityCatalog
{
    /** @return list<array{component:string,criticality:string,impact:string,dependencies:list<string>,degraded_behavior:string,recovery_prerequisite:string}> */
    public function entries(): array
    {
        return [
            ['component' => 'mysql', 'criticality' => ServiceCriticality::Tier0->value, 'impact' => 'Durable reads and writes unavailable', 'dependencies' => [], 'degraded_behavior' => 'Liveness remains; readiness fails safely', 'recovery_prerequisite' => 'Verified storage and schema integrity'],
            ['component' => 'core_http', 'criticality' => ServiceCriticality::Tier0->value, 'impact' => 'Administrative application unavailable', 'dependencies' => ['mysql'], 'degraded_behavior' => 'Safe health endpoint only where runtime remains alive', 'recovery_prerequisite' => 'Healthy immutable image and database'],
            ['component' => 'redis', 'criticality' => ServiceCriticality::Tier1->value, 'impact' => 'Cache, session and queue degraded', 'dependencies' => [], 'degraded_behavior' => 'Core liveness remains; readiness fails', 'recovery_prerequisite' => 'Redis process and guarded configuration'],
            ['component' => 'queue_horizon', 'criticality' => ServiceCriticality::Tier1->value, 'impact' => 'Asynchronous processing delayed', 'dependencies' => ['redis', 'mysql'], 'degraded_behavior' => 'Durable queued jobs wait for workers', 'recovery_prerequisite' => 'Redis ready and worker image healthy'],
            ['component' => 'scheduler', 'criticality' => ServiceCriticality::Tier1->value, 'impact' => 'Scheduled operations delayed', 'dependencies' => ['redis', 'mysql'], 'degraded_behavior' => 'Stale heartbeat is reported', 'recovery_prerequisite' => 'Single scheduler lock authority'],
            ['component' => 'reverb', 'criticality' => ServiceCriticality::Tier2->value, 'impact' => 'Realtime updates unavailable', 'dependencies' => ['redis'], 'degraded_behavior' => 'Core HTTP remains available', 'recovery_prerequisite' => 'Realtime process healthy'],
            ['component' => 'external_adapters', 'criticality' => ServiceCriticality::Tier2->value, 'impact' => 'External delivery delayed', 'dependencies' => ['integration_hub'], 'degraded_behavior' => 'Bounded retry/dead-letter isolation', 'recovery_prerequisite' => 'Credential and provider readiness'],
        ];
    }
}
