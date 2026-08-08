<?php

namespace Modules\Performance\Application\Commands\CreateCareerPath;

use Modules\Performance\Application\DTOs\CareerActionData;

final readonly class CreateCareerPathCommand
{
    public function __construct(public CareerActionData $data) {}
}
