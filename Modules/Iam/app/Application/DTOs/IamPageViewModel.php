<?php

namespace Modules\Iam\Application\DTOs;

final readonly class IamPageViewModel
{
    /**
     * @param  array<int, object>  $records
     * @param  array<string, int|string>  $metrics
     */
    public function __construct(public array $records, public array $metrics = [], public ?object $detail = null) {}
}
