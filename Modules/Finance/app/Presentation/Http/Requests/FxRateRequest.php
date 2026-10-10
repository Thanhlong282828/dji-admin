<?php

namespace Modules\Finance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Domain\Enums\SupportedCurrency;
use Modules\Finance\Domain\Enums\FxRateType;

final class FxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'min:1'], 'action' => ['required', 'in:create,submit,approve,activate,retire'],
            'rate_id' => ['nullable', 'integer', 'min:1'], 'base_currency' => ['nullable', 'string', Rule::in(SupportedCurrency::values())],
            'quote_currency' => ['nullable', 'string', Rule::in(SupportedCurrency::values())], 'rate_decimal' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,12})?$/'],
            'rate_date' => ['nullable', 'date'], 'rate_type' => ['nullable', Rule::enum(FxRateType::class)], 'source_reference' => ['nullable', 'string', 'max:180'],
            'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
