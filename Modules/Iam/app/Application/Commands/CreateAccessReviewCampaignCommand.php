<?php

namespace Modules\Iam\Application\Commands;

final readonly class CreateAccessReviewCampaignCommand
{
    public function __construct(public int $companyId, public string $name, public string $scopeType, public ?int $scopeId, public string $dueDate, public int $actorId, public string $idempotencyKey) {}
}
