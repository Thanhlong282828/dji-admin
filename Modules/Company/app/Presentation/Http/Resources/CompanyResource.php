<?php

namespace Modules\Company\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Company\Application\DTOs\CompanyViewModel;
use Modules\Company\Domain\Models\Company;

final class CompanyResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $company = $this->resource;
        if (! $company instanceof Company) {
            return [];
        }

        return (array) CompanyViewModel::fromModel($company);
    }
}
