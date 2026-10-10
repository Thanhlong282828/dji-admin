<?php

namespace Modules\Tax\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VatPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        $write = in_array($this->input('action'), ['tax-policy.create', 'tax-policy.update'], true);

        return [
            'company_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:tax-policy.create,tax-policy.update,tax-policy.approve,tax-policy.activate,tax-policy.expire'],
            'policy_id' => [$write && $this->input('action') === 'tax-policy.create' ? 'nullable' : 'required', 'integer', 'min:1'],
            'policy_key' => [$write ? 'required' : 'nullable', 'string', 'max:100'],
            'tax_category_id' => [$write ? 'required' : 'nullable', 'integer', 'min:1'],
            'country_code' => [$write ? 'required' : 'nullable', 'string', 'size:2'],
            'currency' => [$write ? 'required' : 'nullable', 'regex:/^[A-Z]{3}$/'],
            'rate_basis_points' => [$write ? 'required' : 'nullable', 'integer', 'min:1', 'max:10000'],
            'effective_from' => [$write ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'tax_account_id' => [$write ? 'required' : 'nullable', 'integer', 'min:1'],
            'accounting_posting_policy_id' => [$write ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
