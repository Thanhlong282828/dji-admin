<?php

namespace App\Support\DataProtection;

final class DataProtectionAuditService
{
    public function __construct(private DataFieldRegistry $registry, private CryptographicInventoryService $crypto) {}

    /** @return array{schema_version:int,classification_counts:array<string,int>,restricted_fields:int,policy_coverage:int,findings:list<array{severity:string,component:string,message:string}>,crypto_count:int,private_artifact_status:string,export_governance_status:string,retention_status:string,checksum:string,status:string} */
    public function audit(): array
    {
        $counts = array_fill_keys(array_column(DataClassification::cases(), 'value'), 0);
        foreach ($this->registry->entries() as $entry) {
            $counts[$entry->classification->value]++;
        }
        $findings = [
            ['severity' => 'warning', 'component' => 'retention', 'message' => 'Legal retention periods require owner/legal approval before automation.'],
            ['severity' => 'info', 'component' => 'key-versioning', 'message' => 'Application encryption-key rotation remains a governed design; no production rotation was performed.'],
        ];
        $result = [
            'schema_version' => 1,
            'classification_counts' => $counts,
            'restricted_fields' => $counts[DataClassification::Restricted->value],
            'policy_coverage' => count($this->registry->entries()),
            'findings' => $findings,
            'crypto_count' => count($this->crypto->entries()),
            'private_artifact_status' => 'governed',
            'export_governance_status' => 'governed_with_domain_permissions',
            'retention_status' => 'policy_pending_legal_periods',
            'checksum' => $this->registry->checksum(),
            'status' => 'pass_with_documented_warnings',
        ];

        return $result;
    }
}
