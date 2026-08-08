<?php

namespace Modules\Performance\Application\Commands\AssessSuccessor;

use Modules\Performance\Application\DTOs\CareerActionData;

final readonly class AssessSuccessorCommand
{
    public function __construct(public CareerActionData $data) {}
}
