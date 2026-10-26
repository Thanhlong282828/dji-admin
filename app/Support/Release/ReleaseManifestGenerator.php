<?php

namespace App\Support\Release;

use RuntimeException;

final class ReleaseManifestGenerator
{
    /** @return array{schema_version:int,release_id:string,git_commit:string,git_tag:string,source:array{state:string,checksum:string},image:array{reference:string,digest:string},created_at:string,environment:string,migration_inventory:array{count:int,checksum:string},runtime_configuration:array{schema_version:int,snapshot_checksum:string,drift_status:string},secret_readiness:array{schema_version:int,metadata_checksum:string,status:string},data_protection:array{schema_version:int,policy_checksum:string,audit_status:string},application_version:string,runtime:array{php:string,laravel:string},quality_gates:array<string,string>,approval_reference:string,rollback_target:string,checksum:string} */
    public function generate(ReleaseManifestData $data): array
    {
        $this->validate($data);
        $manifest = [
            'schema_version' => 1,
            'release_id' => $data->releaseId,
            'git_commit' => $data->gitCommit,
            'git_tag' => $data->gitTag,
            'source' => ['state' => $data->sourceState, 'checksum' => $data->sourceChecksum],
            'image' => ['reference' => $data->imageReference, 'digest' => $data->imageDigest],
            'created_at' => $data->createdAt,
            'environment' => $data->environment,
            'migration_inventory' => ['count' => $data->migrationCount, 'checksum' => $data->migrationInventoryChecksum],
            'runtime_configuration' => ['schema_version' => $data->runtimeConfigSchemaVersion, 'snapshot_checksum' => $data->runtimeConfigChecksum, 'drift_status' => $data->runtimeConfigDriftStatus],
            'secret_readiness' => ['schema_version' => $data->secretSchemaVersion, 'metadata_checksum' => $data->secretReadinessChecksum, 'status' => $data->secretReadinessStatus],
            'data_protection' => ['schema_version' => $data->dataProtectionSchemaVersion, 'policy_checksum' => $data->dataProtectionPolicyChecksum, 'audit_status' => $data->dataProtectionAuditStatus],
            'application_version' => $data->applicationVersion,
            'runtime' => ['php' => $data->phpVersion, 'laravel' => $data->laravelVersion],
            'quality_gates' => $data->qualityGates,
            'approval_reference' => $data->approvalReference,
            'rollback_target' => $data->rollbackTarget,
        ];
        ksort($manifest['quality_gates']);
        $manifest['checksum'] = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $manifest;
    }

    /** @param array<string, mixed> $manifest */
    public function writeImmutable(array $manifest, string $path): void
    {
        $encoded = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        if (is_file($path)) {
            $existing = file_get_contents($path);
            if ($existing !== $encoded) {
                throw new RuntimeException('Release manifest already exists with different content.');
            }

            return;
        }
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create release manifest directory.');
        }
        if (file_put_contents($path, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write release manifest.');
        }
    }

    private function validate(ReleaseManifestData $data): void
    {
        if (preg_match('/^[a-zA-Z0-9._-]{3,128}$/', $data->releaseId) !== 1) {
            throw new RuntimeException('Invalid release ID.');
        }
        if (preg_match('/^[0-9a-f]{40}$/', $data->gitCommit) !== 1) {
            throw new RuntimeException('A full lowercase Git commit SHA is required.');
        }
        if (! in_array($data->sourceState, ['committed', 'working_tree'], true) || preg_match('/^[0-9a-f]{64}$/', $data->sourceChecksum) !== 1) {
            throw new RuntimeException('A valid source state and SHA-256 checksum are required.');
        }
        if (! in_array($data->environment, ['development', 'testing', 'staging', 'production'], true)) {
            throw new RuntimeException('Invalid release environment.');
        }
        if (preg_match('/^sha256:[0-9a-f]{64}$/', $data->imageDigest) !== 1 || ! str_contains($data->imageReference, ':')) {
            throw new RuntimeException('Immutable image tag and SHA-256 digest are required.');
        }
        if ($data->rollbackTarget === $data->imageReference || $data->rollbackTarget === '') {
            throw new RuntimeException('A distinct rollback image target is required.');
        }
        if (preg_match('/^[0-9a-f]{64}$/', $data->migrationInventoryChecksum) !== 1) {
            throw new RuntimeException('Migration inventory checksum is required.');
        }
        if ($data->runtimeConfigSchemaVersion < 1 || preg_match('/^[0-9a-f]{64}$/', $data->runtimeConfigChecksum) !== 1 || ! in_array($data->runtimeConfigDriftStatus, ['clean', 'intentional', 'drift'], true)) {
            throw new RuntimeException('Valid non-secret runtime configuration evidence is required.');
        }
        if ($data->secretSchemaVersion < 1 || preg_match('/^[0-9a-f]{64}$/', $data->secretReadinessChecksum) !== 1 || ! in_array($data->secretReadinessStatus, ['ready', 'missing'], true)) {
            throw new RuntimeException('Valid secret presence metadata evidence is required.');
        }
        if ($data->dataProtectionSchemaVersion < 1 || preg_match('/^[0-9a-f]{64}$/', $data->dataProtectionPolicyChecksum) !== 1 || ! in_array($data->dataProtectionAuditStatus, ['pass', 'pass_with_documented_warnings', 'fail'], true)) {
            throw new RuntimeException('Valid data-protection policy evidence is required.');
        }
        foreach ($data->qualityGates as $gate => $status) {
            if ($gate === '' || ! in_array($status, ['pass', 'not_applicable'], true)) {
                throw new RuntimeException('Release quality gates must be pass or not_applicable.');
            }
        }
    }
}
