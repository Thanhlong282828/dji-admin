<?php

namespace Modules\Workflow\Domain\Services;

final class WorkflowRoutingService
{
    /**
     * @param  array<string, scalar|null>  $context
     * @param  array<string, scalar|null>  $condition
     */
    public function matches(array $context, array $condition): bool
    {
        if ($condition === []) {
            return true;
        }
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expected = $condition['value'] ?? null;
        if (! is_string($field) || ! array_key_exists($field, $context)) {
            return false;
        }
        $actual = $context[$field];

        return match ($operator) {
            'equals', 'eq' => $actual === $expected,
            'not_equals', 'neq' => $actual !== $expected,
            'greater_than', 'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            default => false,
        };
    }
}
