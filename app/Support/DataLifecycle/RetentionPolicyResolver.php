<?php

namespace App\Support\DataLifecycle;

use DateTimeImmutable;

final class RetentionPolicyResolver
{
    /** @param list<RetentionPolicy> $policies */
    public function resolve(array $policies, string $resourceType, DateTimeImmutable $at): ?RetentionPolicy
    {
        $applicable = array_filter($policies, static fn (RetentionPolicy $policy): bool => $policy->resourceType === $resourceType && $policy->enabled && $policy->effectiveFrom <= $at && ($policy->effectiveTo === null || $policy->effectiveTo >= $at));
        usort($applicable, static fn (RetentionPolicy $left, RetentionPolicy $right): int => [$right->effectiveFrom->getTimestamp(), $right->version] <=> [$left->effectiveFrom->getTimestamp(), $left->version]);

        return $applicable[0] ?? null;
    }
}
