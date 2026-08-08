<?php

namespace Modules\Employee\Domain\Governance;

interface HRDataQualityRule
{
    public function code(): string;

    public function severity(): string;

    /** @return list<array{entity_type:string,entity_id:int,message:string}> */
    public function evaluate(int $companyId): array;
}
