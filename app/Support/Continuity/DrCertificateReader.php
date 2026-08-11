<?php

namespace App\Support\Continuity;

final class DrCertificateReader
{
    /** @return array{available:bool,integrity:bool,status:string,exercise_id:string,completed_at:string,scope:string,checksum:string,gate_results:array<string,bool|int|float|string>,rto:array<string,bool|int|float|string>,rpo:array<string,bool|int|float|string>} */
    public function latest(): array
    {
        $files = glob(storage_path('dr-certificates/*.json'));
        if (! is_array($files) || $files === []) {
            return $this->empty();
        }
        usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        $decoded = json_decode((string) file_get_contents($files[0]), true);
        if (! is_array($decoded)) {
            return $this->empty();
        }
        $checksum = $decoded['checksum'] ?? null;
        unset($decoded['checksum']);
        $integrity = is_string($checksum) && hash_equals($checksum, hash('sha256', json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));

        return ['available' => true, 'integrity' => $integrity, 'status' => $this->text($decoded['status'] ?? null), 'exercise_id' => $this->text($decoded['exercise_id'] ?? null), 'completed_at' => $this->text($decoded['completed_at'] ?? null), 'scope' => $this->text($decoded['scope'] ?? null), 'checksum' => is_string($checksum) ? $checksum : '', 'gate_results' => $this->evidence($decoded['gate_results'] ?? null), 'rto' => $this->evidence($decoded['rto'] ?? null), 'rpo' => $this->evidence($decoded['rpo'] ?? null)];
    }

    /** @return array{available:bool,integrity:bool,status:string,exercise_id:string,completed_at:string,scope:string,checksum:string,gate_results:array<string,bool|int|float|string>,rto:array<string,bool|int|float|string>,rpo:array<string,bool|int|float|string>} */
    private function empty(): array
    {
        return ['available' => false, 'integrity' => false, 'status' => 'not_exercised', 'exercise_id' => '', 'completed_at' => '', 'scope' => '', 'checksum' => '', 'gate_results' => [], 'rto' => [], 'rpo' => []];
    }

    /** @return array<string, bool|int|float|string> */
    private function evidence(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $safe = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && (is_bool($item) || is_int($item) || is_float($item) || is_string($item))) {
                $safe[$key] = $item;
            }
        }

        return $safe;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
