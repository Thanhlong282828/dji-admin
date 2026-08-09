<?php

namespace App\Support\Release;

final class ReleaseCertificateReader
{
    /** @return array{available:bool,integrity:bool,status:string,release_id:string,git_commit:string,image_digest:string,completed_at:string,rollback_target:string,rollback_compatible:bool,gate_results:array<string,bool|int|float|string>,health_results:array<string,bool|int|float|string>} */
    public function latest(): array
    {
        $files = glob(storage_path('release-certificates/*.json'));
        if (! is_array($files) || $files === []) {
            return $this->empty();
        }
        usort($files, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));
        $decoded = json_decode((string) file_get_contents($files[0]), true);
        if (! is_array($decoded)) {
            return $this->empty();
        }
        $checksum = $decoded['checksum'] ?? null;
        unset($decoded['checksum']);
        $integrity = is_string($checksum) && hash_equals($checksum, hash('sha256', json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));

        return [
            'available' => true,
            'integrity' => $integrity,
            'status' => $this->text($decoded['status'] ?? null),
            'release_id' => $this->text($decoded['release_id'] ?? null),
            'git_commit' => $this->text($decoded['git_commit'] ?? null),
            'image_digest' => $this->text($decoded['image_digest'] ?? null),
            'completed_at' => $this->text($decoded['completed_at'] ?? null),
            'rollback_target' => $this->text($decoded['rollback_target'] ?? null),
            'rollback_compatible' => ($decoded['rollback_compatible'] ?? false) === true,
            'gate_results' => $this->evidence($decoded['gate_results'] ?? null),
            'health_results' => $this->evidence($decoded['health_results'] ?? null),
        ];
    }

    /** @return array{available:bool,integrity:bool,status:string,release_id:string,git_commit:string,image_digest:string,completed_at:string,rollback_target:string,rollback_compatible:bool,gate_results:array<string,bool|int|float|string>,health_results:array<string,bool|int|float|string>} */
    private function empty(): array
    {
        return ['available' => false, 'integrity' => false, 'status' => 'not_certified', 'release_id' => '', 'git_commit' => '', 'image_digest' => '', 'completed_at' => '', 'rollback_target' => '', 'rollback_compatible' => false, 'gate_results' => [], 'health_results' => []];
    }

    /** @return array<string, bool|int|float|string> */
    private function evidence(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && (is_bool($item) || is_int($item) || is_float($item) || is_string($item))) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
