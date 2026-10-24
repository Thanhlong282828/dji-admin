<?php

namespace App\Support\DataQuality;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final readonly class StagingRestoreAuditAuthorizer
{
    public function __construct(
        private StagingRuntimeIdentityProvider $runtimeIdentity,
        private StagingDatabaseIdentityProvider $databaseIdentity,
        private StagingTrustEvidenceProvider $trustEvidence,
    ) {}

    /**
     * @param  array<string,mixed>  $scope
     * @return array{manifest_sha256:string,server_uuid:string,release_sha:string,backup_artifact_sha256:string,project_id:string,instance_id:string,network:string,subnet:string,public_ip_absent:bool,database_read_only:bool,expires_at:string}
     */
    public function authorize(array $scope, string $expectedDatabase): array
    {
        if (($scope['environment'] ?? null) !== 'staging') {
            throw new RuntimeException('Staging restore authorization requires APP_ENV=staging.');
        }
        $canonicalDatabase = $this->configuredString('data-quality.staging_restore.database');
        if ($canonicalDatabase !== 'laraveldji_staging_restore' || ! hash_equals($canonicalDatabase, $expectedDatabase)) {
            throw new RuntimeException('Staging audit requires the canonical laraveldji_staging_restore database.');
        }
        if (($scope['database_purpose'] ?? null) !== 'STAGING_RESTORE' || ($scope['database_matches'] ?? null) !== true
            || ! hash_equals($expectedDatabase, $this->scopeString($scope, 'configured_database'))
            || ! hash_equals($expectedDatabase, $this->scopeString($scope, 'actual_database'))) {
            throw new RuntimeException('Expected, configured and actual staging database identities must match.');
        }

        $evidence = $this->trustEvidence->evidence();
        $manifest = $evidence['approval'];
        $this->assertManifestContract($manifest, $evidence['release'], $evidence['restore'], $expectedDatabase);

        $database = $this->databaseIdentity->identity();
        $serverUuid = $this->uuid($database['server_uuid'], 'actual database server UUID');
        $manifestServerUuid = $this->uuid($this->value($manifest, 'database.server_uuid'), 'manifest database server UUID');
        $productionUuid = $this->uuid($this->value($evidence['restore'], 'production_server_uuid'), 'trusted production server UUID');
        if (! hash_equals($manifestServerUuid, $serverUuid) || hash_equals($productionUuid, $serverUuid)
            || ! hash_equals($productionUuid, $this->uuid($this->value($manifest, 'database.production_server_uuid'), 'approved production server UUID'))) {
            throw new RuntimeException('Staging server UUID mismatch or production UUID detected.');
        }
        if (! $this->readOnlyGrants($database['grants'], $expectedDatabase)) {
            throw new RuntimeException('Staging database credential is not restricted to required read-only grants.');
        }

        $runtime = $this->runtimeIdentity->identity();
        foreach (['project_id', 'instance_id'] as $key) {
            if (! hash_equals($this->requiredString($manifest, 'gcp.'.$key), $runtime[$key])) {
                throw new RuntimeException('Staging runtime identity mismatch: '.$key.'.');
            }
        }
        if (! $this->sameStringList($this->value($manifest, 'gcp.networks'), $runtime['networks'])
            || ! $this->sameStringList($this->value($manifest, 'gcp.subnets'), $runtime['subnets'])) {
            throw new RuntimeException('Staging runtime network identity mismatch.');
        }
        if ($runtime['public_ips'] !== [] || $this->value($manifest, 'gcp.public_ip_absent') !== true) {
            throw new RuntimeException('Staging audit requires a runtime with no public IP.');
        }

        return [
            'manifest_sha256' => $evidence['approval_sha256'],
            'server_uuid' => $serverUuid,
            'release_sha' => $this->requiredString($manifest, 'release_sha'),
            'backup_artifact_sha256' => $this->requiredString($manifest, 'backup_artifact_sha256'),
            'project_id' => (string) $runtime['project_id'],
            'instance_id' => (string) $runtime['instance_id'],
            'network' => implode(',', $runtime['networks']),
            'subnet' => implode(',', $runtime['subnets']),
            'public_ip_absent' => true,
            'database_read_only' => true,
            'expires_at' => $this->requiredString($manifest, 'expires_at'),
        ];
    }

    /**
     * @param  array<string,mixed>  $manifest
     * @param  array<string,mixed>  $releaseIdentity
     * @param  array<string,mixed>  $restoreReceipt
     */
    private function assertManifestContract(array $manifest, array $releaseIdentity, array $restoreReceipt, string $database): void
    {
        if ($this->value($manifest, 'schema_version') !== 1 || $this->value($manifest, 'mode') !== 'DRY_RUN_READ_ONLY') {
            throw new RuntimeException('Authorization manifest must approve schema version 1 and DRY_RUN_READ_ONLY mode.');
        }
        if (! hash_equals($database, $this->requiredString($manifest, 'database.name')) || $this->value($manifest, 'database.read_only') !== true) {
            throw new RuntimeException('Authorization manifest database contract is invalid.');
        }

        $approvedRelease = strtolower($this->requiredString($manifest, 'release_sha'));
        $immutableRelease = strtolower($this->requiredString($releaseIdentity, 'git_sha'));
        if (preg_match('/^[a-f0-9]{40}$/', $approvedRelease) !== 1 || $this->value($releaseIdentity, 'immutable') !== true || ! hash_equals($approvedRelease, $immutableRelease)) {
            throw new RuntimeException('Git release SHA does not match the authorization manifest.');
        }
        $backup = strtolower($this->requiredString($manifest, 'backup_artifact_sha256'));
        $restoredBackup = strtolower($this->requiredString($restoreReceipt, 'backup_artifact_sha256'));
        if (preg_match('/^[a-f0-9]{64}$/', $backup) !== 1 || $this->value($restoreReceipt, 'restore_verified') !== true || ! hash_equals($backup, $restoredBackup)) {
            throw new RuntimeException('Backup artifact SHA-256 does not match the authorization manifest.');
        }

        try {
            $expiresAt = new DateTimeImmutable($this->requiredString($manifest, 'expires_at'));
        } catch (\Exception $exception) {
            throw new RuntimeException('Authorization manifest expiry is invalid.', previous: $exception);
        }
        if ($expiresAt <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new RuntimeException('Authorization manifest has expired.');
        }
    }

    /** @param list<string> $grants */
    private function readOnlyGrants(array $grants, string $database): bool
    {
        $hasSelect = false;
        foreach ($grants as $grant) {
            if (str_contains(strtoupper($grant), 'WITH GRANT OPTION')) {
                return false;
            }
            if (preg_match('/^GRANT (.+) ON (.+) TO /i', $grant, $matches) !== 1) {
                return false;
            }
            $privileges = array_map(static fn (string $value): string => strtoupper(trim($value)), explode(',', $matches[1]));
            $target = str_replace('`', '', trim($matches[2]));
            if ($target === '*.*' && $privileges === ['USAGE']) {
                continue;
            }
            if ($target !== $database.'.*' || array_diff($privileges, ['SELECT', 'SHOW VIEW']) !== []) {
                return false;
            }
            $hasSelect = $hasSelect || in_array('SELECT', $privileges, true);
        }

        return $hasSelect;
    }

    /** @param array<string,mixed> $manifest */
    private function requiredString(array $manifest, string $path): string
    {
        $value = $this->value($manifest, $path);
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('Authorization manifest value is required: '.$path.'.');
        }

        return trim($value);
    }

    /** @param array<string,mixed> $manifest */
    private function value(array $manifest, string $path): mixed
    {
        $value = $manifest;
        foreach (explode('.', $path) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    private function uuid(mixed $value, string $label): string
    {
        if (! is_string($value) || preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $value) !== 1) {
            throw new RuntimeException(ucfirst($label).' is invalid.');
        }

        return strtolower($value);
    }

    /** @param array<string,mixed> $scope */
    private function scopeString(array $scope, string $key): string
    {
        $value = $scope[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    private function configuredString(string $key): string
    {
        $value = config($key);

        return is_string($value) ? trim($value) : '';
    }

    /** @param list<string> $actual */
    private function sameStringList(mixed $expected, array $actual): bool
    {
        if (! is_array($expected)) {
            return false;
        }
        $values = [];
        foreach ($expected as $value) {
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
            $values[] = trim($value);
        }
        $values = array_values(array_unique($values));
        sort($values);

        return $values === $actual;
    }
}
