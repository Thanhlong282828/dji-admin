<?php

namespace App\Support\Release;

final class ReleaseManifestReader
{
    /** @return array{available:bool,integrity:bool,release_id:string,git_commit:string,git_tag:string,image_reference:string,image_digest:string,environment:string,migration_count:int,quality_gates:array<string,string>,rollback_target:string,created_at:string} */
    public function current(): array
    {
        $directory = config('release.manifest_directory');
        $files = is_string($directory) ? glob(rtrim($directory, '/').'/*.json') : false;
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
        $image = is_array($decoded['image'] ?? null) ? $decoded['image'] : [];
        $quality = [];
        foreach (is_array($decoded['quality_gates'] ?? null) ? $decoded['quality_gates'] : [] as $gate => $status) {
            if (is_string($gate) && is_string($status)) {
                $quality[$gate] = $status;
            }
        }

        return [
            'available' => true,
            'integrity' => $integrity,
            'release_id' => $this->text($decoded['release_id'] ?? null),
            'git_commit' => $this->text($decoded['git_commit'] ?? null),
            'git_tag' => $this->text($decoded['git_tag'] ?? null),
            'image_reference' => $this->text($image['reference'] ?? null),
            'image_digest' => $this->text($image['digest'] ?? null),
            'environment' => $this->text($decoded['environment'] ?? null),
            'migration_count' => is_numeric($decoded['migration_count'] ?? null) ? (int) $decoded['migration_count'] : 0,
            'quality_gates' => $quality,
            'rollback_target' => $this->text($decoded['rollback_target'] ?? null),
            'created_at' => $this->text($decoded['created_at'] ?? null),
        ];
    }

    /** @return array{available:bool,integrity:bool,release_id:string,git_commit:string,git_tag:string,image_reference:string,image_digest:string,environment:string,migration_count:int,quality_gates:array<string,string>,rollback_target:string,created_at:string} */
    private function empty(): array
    {
        return ['available' => false, 'integrity' => false, 'release_id' => '', 'git_commit' => '', 'git_tag' => '', 'image_reference' => '', 'image_digest' => '', 'environment' => '', 'migration_count' => 0, 'quality_gates' => [], 'rollback_target' => '', 'created_at' => ''];
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
