<?php

namespace Modules\Performance\Application\Commands\UpdateCareerProgress;

use Modules\Performance\Application\DTOs\CareerActionData;

final readonly class UpdateCareerProgressCommand
{
    public function __construct(public CareerActionData $data) {}
}
