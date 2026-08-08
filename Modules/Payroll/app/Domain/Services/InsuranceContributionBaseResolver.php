<?php

namespace Modules\Payroll\Domain\Services;

use Modules\Payroll\Application\DTOs\InsuranceContributionBase;

final class InsuranceContributionBaseResolver
{
    /**
     * @param  list<array{id:int,amount_minor:int,insurance_status:string,currency:string}>  $components
     */
    public function resolve(array $components, ?int $overrideMinor, string $currency): InsuranceContributionBase
    {
        $raw = 0;
        $included = [];
        $excluded = [];
        foreach ($components as $component) {
            if ($component['insurance_status'] === 'included') {
                $raw += $component['amount_minor'];
                $included[] = $component['id'];
            } elseif ($component['insurance_status'] === 'excluded') {
                $excluded[] = $component['id'];
            }
        }
        $final = $overrideMinor ?? $raw;

        return new InsuranceContributionBase($raw, $included, $excluded, $overrideMinor, $final, $currency, $overrideMinor === null ? 'eligible_components' : 'approved_profile_override');
    }
}
