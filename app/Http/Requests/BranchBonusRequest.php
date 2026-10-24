<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BranchBonusRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $action = (string) $this->route('action', 'create');
        if ($action !== 'create') {
            return ['company_id' => ['required', 'integer', 'min:1'], 'version' => ['required', 'integer', 'min:1'], 'comment' => [$action === 'reject' ? 'required' : 'nullable', 'string', 'max:500']];
        }

        return [
            'company_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['required', 'integer', 'min:1'],
            'employee_id' => ['required', 'integer', 'min:1'],
            'bonus_reference' => ['required', 'string', 'max:80'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reason_category' => ['required', Rule::in(['performance', 'sales', 'project', 'retention', 'recognition', 'other'])],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'effective_period' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
