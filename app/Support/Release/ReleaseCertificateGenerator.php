<?php

namespace App\Support\Release;

use RuntimeException;

final class ReleaseCertificateGenerator
{
    /** @return array{schema_version:int,scope:string,release_id:string,manifest_checksum:string,git_commit:string,image_digest:string,migration_inventory:array{count:int,checksum:string},certification_environment:string,started_at:string,completed_at:string,gate_results:array<string,bool|int|float|string>,health_results:array<string,bool|int|float|string>,rollback_target:string,rollback_compatible:bool,quality_reference:string,browser_result:string,status:string,checksum:string} */
    public function generate(ReleaseCertificateData $data): array
    {
        foreach ([$data->manifestChecksum, $data->migrationInventoryChecksum] as $checksum) {
            if (preg_match('/^[0-9a-f]{64}$/', $checksum) !== 1) {
                throw new RuntimeException('Certificate checksums must be SHA-256 hex.');
            }
        }
        if (preg_match('/^[0-9a-f]{40}$/', $data->gitCommit) !== 1 || preg_match('/^sha256:[0-9a-f]{64}$/', $data->imageDigest) !== 1) {
            throw new RuntimeException('Certificate Git/image identity is invalid.');
        }
        $gateResults = $data->gateResults;
        $healthResults = $data->healthResults;
        ksort($gateResults);
        ksort($healthResults);
        $passed = $data->rollbackCompatible && ! in_array(false, $gateResults, true) && ! in_array(false, $healthResults, true) && $data->browserResult === 'pass';
        $certificate = [
            'schema_version' => 1,
            'scope' => 'testing-production-like-certification-only',
            'release_id' => $data->releaseId,
            'manifest_checksum' => $data->manifestChecksum,
            'git_commit' => $data->gitCommit,
            'image_digest' => $data->imageDigest,
            'migration_inventory' => ['count' => $data->migrationCount, 'checksum' => $data->migrationInventoryChecksum],
            'certification_environment' => $data->environment,
            'started_at' => $data->startedAt,
            'completed_at' => $data->completedAt,
            'gate_results' => $gateResults,
            'health_results' => $healthResults,
            'rollback_target' => $data->rollbackTarget,
            'rollback_compatible' => $data->rollbackCompatible,
            'quality_reference' => $data->qualityReference,
            'browser_result' => $data->browserResult,
            'status' => $passed ? 'certified' : 'failed',
        ];
        $certificate['checksum'] = hash('sha256', json_encode($certificate, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $certificate;
    }

    /** @param array<string, mixed> $certificate */
    public function writeImmutable(array $certificate, string $path): void
    {
        (new ReleaseManifestGenerator)->writeImmutable($certificate, $path);
    }
}
