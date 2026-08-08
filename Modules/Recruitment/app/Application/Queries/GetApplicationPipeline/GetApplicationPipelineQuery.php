<?php

namespace Modules\Recruitment\Application\Queries\GetApplicationPipeline;

final readonly class GetApplicationPipelineQuery
{
    public function __construct(public int $companyId, public ?int $requisitionId = null) {}
}
