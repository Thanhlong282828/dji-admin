<?php

namespace Tests\Unit\Organization;

use Illuminate\Validation\ValidationException;
use Modules\Organization\Domain\Services\OrganizationHierarchyValidator;
use Tests\TestCase;

final class OrganizationHierarchyValidatorTest extends TestCase
{
    public function test_it_allows_descending_hierarchy(): void
    {
        $this->expectNotToPerformAssertions();
        (new OrganizationHierarchyValidator)->validate('division', 'department');
    }

    public function test_it_rejects_parent_below_child(): void
    {
        $this->expectException(ValidationException::class);
        (new OrganizationHierarchyValidator)->validate('team', 'department');
    }
}
