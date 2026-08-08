<?php

namespace Modules\Employee\Domain\Governance;

final readonly class HRDataQualityRuleRegistry
{
    /** @return list<HRDataQualityRule> */
    public function rules(): array
    {
        return [new EmployeeWithoutPositionRule, new EmployeeWithoutManagerRule, new InvalidContractRule, new MissingDocumentRule, new DuplicateIdentityRule];
    }
}
