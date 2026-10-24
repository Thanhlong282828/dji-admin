<?php

namespace Modules\Finance\Application\Queries;

final readonly class GetTreasuryCashPositionQuery
{
    public function __construct(public int $companyId, public string $asOfDate) {}
}
