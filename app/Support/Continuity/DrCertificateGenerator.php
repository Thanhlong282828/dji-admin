<?php

namespace App\Support\Continuity;

use RuntimeException;

final class DrCertificateGenerator
{
    /** @param array<string, bool|int|float|string> $backup
     * @param  array<string, bool|int|float|string>  $restore
     * @param  array<string, bool|int|float|string>  $recovery
     * @param  array<string, bool|int|float|string>  $rto
     * @param  array<string, bool|int|float|string>  $rpo
     * @param  array<string, bool|int|float|string>  $gates
     * @return array{schema_version:int,scope:string,exercise_id:string,environment:string,source_git_sha:string,started_at:string,completed_at:string,backup:array<string,bool|int|float|string>,restore:array<string,bool|int|float|string>,recovery:array<string,bool|int|float|string>,rto:array<string,bool|int|float|string>,rpo:array<string,bool|int|float|string>,gate_results:array<string,bool|int|float|string>,status:string,checksum:string}
     */
    public function generate(string $exerciseId, string $environment, string $gitSha, string $startedAt, string $completedAt, array $backup, array $restore, array $recovery, array $rto, array $rpo, array $gates): array
    {
        if (preg_match('/^[a-zA-Z0-9._-]{3,128}$/', $exerciseId) !== 1 || $environment !== 'testing_dr_exercise' || preg_match('/^[0-9a-f]{7,40}$/', $gitSha) !== 1) {
            throw new RuntimeException('Invalid testing DR certificate identity.');
        }
        foreach ([$backup, $restore, $recovery, $rto, $rpo, $gates] as &$values) {
            ksort($values);
        }
        unset($values);
        $passed = ! in_array(false, $gates, true);
        $certificate = ['schema_version' => 1, 'scope' => 'testing_only_not_production_certification', 'exercise_id' => $exerciseId, 'environment' => $environment, 'source_git_sha' => $gitSha, 'started_at' => $startedAt, 'completed_at' => $completedAt, 'backup' => $backup, 'restore' => $restore, 'recovery' => $recovery, 'rto' => $rto, 'rpo' => $rpo, 'gate_results' => $gates, 'status' => $passed ? 'certified' : 'failed'];
        $certificate['checksum'] = hash('sha256', json_encode($certificate, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $certificate;
    }

    /** @param array<string, mixed> $certificate */
    public function writeImmutable(array $certificate, string $path): void
    {
        $encoded = json_encode($certificate, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        if (is_file($path)) {
            if (file_get_contents($path) !== $encoded) {
                throw new RuntimeException('DR certificate already exists with different content.');
            }

            return;
        }
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0750, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Unable to create DR certificate directory.');
        }
        if (file_put_contents($path, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write DR certificate.');
        }
    }
}
