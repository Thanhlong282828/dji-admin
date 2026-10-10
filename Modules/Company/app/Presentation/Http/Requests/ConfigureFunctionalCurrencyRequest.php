<?php

namespace Modules\Company\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Domain\Enums\SupportedCurrency;

final class ConfigureFunctionalCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.update') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'functional_currency_code' => ['required', 'string', Rule::enum(SupportedCurrency::class)],
            'confirm_immutable' => ['required', 'accepted'],
        ];
    }
}
