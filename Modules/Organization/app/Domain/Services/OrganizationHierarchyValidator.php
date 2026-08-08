<?php

namespace Modules\Organization\Domain\Services;

use Illuminate\Validation\ValidationException;

final class OrganizationHierarchyValidator
{
    private const LEVEL = ['company' => 0, 'branch' => 1, 'division' => 2, 'department' => 3, 'section' => 4, 'team' => 5];

    public function validate(?string $parentType, string $childType): void
    {
        if ($parentType === null) {
            return;
        }$parent = self::LEVEL[$parentType] ?? -1;
        $child = self::LEVEL[$childType] ?? -1;
        if ($child <= $parent) {
            throw ValidationException::withMessages(['type' => 'Loại đơn vị con phải thấp hơn đơn vị cha trong cây tổ chức.']);
        }
    }
}
