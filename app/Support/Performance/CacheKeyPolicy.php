<?php

namespace App\Support\Performance;

use InvalidArgumentException;

final class CacheKeyPolicy
{
    /** @param array<string, mixed> $filters */
    public function scoped(string $component, int $companyId, int $version, array $filters = []): string
    {
        if ($companyId < 0 || $version < 1 || preg_match('/^[a-z0-9-]+$/', $component) !== 1) {
            throw new InvalidArgumentException('Invalid cache-key scope.');
        }

        $normalized = $this->normalize($filters);

        return "schema:{$component}:company:{$companyId}:v{$version}:".hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $filters
     * @return array<string, bool|float|int|string|null>
     */
    private function normalize(array $filters): array
    {
        $safe = [];
        foreach ($filters as $key => $value) {
            if (preg_match('/password|token|secret|email|phone|salary|tax|bank/i', $key) === 1) {
                continue;
            }
            if (is_bool($value) || is_float($value) || is_int($value) || is_string($value) || $value === null) {
                $safe[$key] = $value;
            }
        }
        ksort($safe);

        return $safe;
    }
}
