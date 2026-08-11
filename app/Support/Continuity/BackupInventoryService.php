<?php

namespace App\Support\Continuity;

final class BackupInventoryService
{
    /** @return list<array{asset:string,classification:string,reason:string}> */
    public function entries(): array
    {
        return [
            ['asset' => 'mysql_schema_and_data', 'classification' => 'must_backup', 'reason' => 'Durable business state'],
            ['asset' => 'private_artifacts', 'classification' => 'must_backup', 'reason' => 'Authorized documents and integrity evidence'],
            ['asset' => 'release_manifests_and_certificates', 'classification' => 'must_backup', 'reason' => 'Release and rollback evidence'],
            ['asset' => 'critical_config_references', 'classification' => 'must_backup', 'reason' => 'Non-secret recovery references'],
            ['asset' => 'application_image', 'classification' => 'rebuildable', 'reason' => 'Reproducible immutable build'],
            ['asset' => 'cache_and_compiled_views', 'classification' => 'ephemeral', 'reason' => 'Regenerated after recovery'],
            ['asset' => 'temporary_files', 'classification' => 'ephemeral', 'reason' => 'Not authoritative'],
        ];
    }
}
