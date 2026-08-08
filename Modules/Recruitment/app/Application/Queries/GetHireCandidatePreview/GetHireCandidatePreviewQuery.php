<?php

namespace Modules\Recruitment\Application\Queries\GetHireCandidatePreview;

final readonly class GetHireCandidatePreviewQuery
{
    public function __construct(public int $companyId, public int $offerId, public bool $canViewSalary) {}
}
