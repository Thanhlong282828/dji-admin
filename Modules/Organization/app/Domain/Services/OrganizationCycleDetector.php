<?php

namespace Modules\Organization\Domain\Services;

use Modules\Organization\Domain\Models\OrganizationUnit;

final class OrganizationCycleDetector
{
    public function wouldCreateCycle(int $unitId, ?int $newParentId): bool
    {
        if ($newParentId === null) {
            return false;
        }if ($unitId === $newParentId) {
            return true;
        }$cursor = $newParentId;
        while ($cursor !== null) {
            $parent = OrganizationUnit::query()->whereKey($cursor)->value('parent_id');
            if ($parent === $unitId) {
                return true;
            }$cursor = is_int($parent) ? $parent : null;
        }

        return false;
    }
}
