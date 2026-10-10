<?php

namespace Modules\Finance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FxRevaluationCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'accounting_period_id' => ['required', 'integer', 'min:1'],
            'as_of_date' => ['required', 'date_format:Y-m-d'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
