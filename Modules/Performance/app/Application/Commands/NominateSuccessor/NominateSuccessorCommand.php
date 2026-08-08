<?php

namespace Modules\Performance\Application\Commands\NominateSuccessor;

use Modules\Performance\Application\DTOs\CareerActionData;

final readonly class NominateSuccessorCommand
{
    public function __construct(public CareerActionData $data) {}
}
