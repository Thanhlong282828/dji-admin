<?php

namespace Modules\Product\Application\Commands;

final readonly class SaveProductCategoryCommand
{
    public function __construct(public string $name, public ?int $parentId, public string $status, public int $sortOrder) {}
}
