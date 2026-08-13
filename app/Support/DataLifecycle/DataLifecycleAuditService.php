<?php

namespace App\Support\DataLifecycle;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DataLifecycleAuditService
{
    public function __construct(private RetentionPolicyRegistry $registry) {}

    /** @return array{schema_version:int,policy_count:int,resource_categories:list<string>,classification_mapping:array<string,int>,configured_retention_periods:int,legal_holds:int,retention_eligible:int,disposal_eligible:int,blocked_disposal:int,archive_artifact_count:int,archive_integrity:string,orphan_artifact_findings:int,dry_run_enabled:bool,destructive_disposal_enabled:bool,policy_checksum:string,findings:list<array{severity:string,component:string,message:string}>,status:string} */
    public function snapshot(): array
    {
        $schemaVersion = config('data-lifecycle.schema_version', 1);
        if (! is_int($schemaVersion)) {
            $schemaVersion = 1;
        }
        $policies = $this->registry->policies();
        $classification = ['public' => 0, 'internal' => 0, 'confidential' => 0, 'restricted' => 0];
        foreach ($policies as $policy) {
            $classification[$policy->classification->value]++;
        }
        $configured = count(array_filter($policies, static fn (RetentionPolicy $policy): bool => $policy->retentionDays !== null));
        $archiveCount = Schema::hasTable('audit_archive_artifacts') ? DB::table('audit_archive_artifacts')->count() : 0;
        $findings = [
            ['severity' => 'warning', 'component' => 'legal-authority', 'message' => 'Retention durations remain unset until owner/legal sources are approved.'],
            ['severity' => 'warning', 'component' => 'legal-hold', 'message' => 'Persistent legal-hold workflow and maker-checker enforcement are intentionally deferred.'],
            ['severity' => 'warning', 'component' => 'object-storage', 'message' => 'Cross-provider orphan object detection requires an owner-specific storage adapter.'],
            ['severity' => 'info', 'component' => 'anonymization', 'message' => 'No irreversible anonymization policy is authorized or executed.'],
        ];

        return [
            'schema_version' => $schemaVersion,
            'policy_count' => count($policies),
            'resource_categories' => array_values(array_unique(array_map(static fn (RetentionPolicy $policy): string => $policy->resourceType, $policies))),
            'classification_mapping' => $classification,
            'configured_retention_periods' => $configured,
            'legal_holds' => 0,
            'retention_eligible' => 0,
            'disposal_eligible' => 0,
            'blocked_disposal' => count($policies),
            'archive_artifact_count' => $archiveCount,
            'archive_integrity' => Schema::hasTable('audit_archive_artifacts') ? 'audit-owned-sha256-metadata' : 'unavailable',
            'orphan_artifact_findings' => 1,
            'dry_run_enabled' => (bool) config('data-lifecycle.dry_run_enabled', true),
            'destructive_disposal_enabled' => (bool) config('data-lifecycle.disposal_enabled', false),
            'policy_checksum' => $this->registry->checksum(),
            'findings' => $findings,
            'status' => 'pass_with_documented_warnings',
        ];
    }
}
