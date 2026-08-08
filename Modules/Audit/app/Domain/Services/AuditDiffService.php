<?php

namespace Modules\Audit\Domain\Services;

final class AuditDiffService
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{before: mixed, after: mixed}>
     */
    public function diff(array $before, array $after): array
    {
        $result = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $result[$key] = ['before' => $before[$key] ?? null, 'after' => $after[$key] ?? null];
            }
        }

        return $result;
    }
}
