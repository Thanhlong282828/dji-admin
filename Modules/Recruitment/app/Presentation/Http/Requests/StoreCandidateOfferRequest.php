<?php

namespace Modules\Recruitment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCandidateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'application_id' => ['required', 'integer'],
            'employment_type' => ['required', 'string', 'max:30'],
            'base_salary' => ['required', 'decimal:0,2', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'in:VND,USD,EUR,SGD,JPY'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'probation_end_date' => ['nullable', 'date', 'after:start_date'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'terms' => ['nullable', 'array'],
        ];
    }
}
