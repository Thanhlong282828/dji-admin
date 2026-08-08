<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PayrollPeriodRequest extends FormRequest
{
    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        if (in_array($this->route()?->getActionMethod(), ['store', 'update'], true)) {
            return ['code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'], 'name' => ['required', 'string', 'max:255'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'pay_date' => ['required', 'date', 'after_or_equal:start_date'], 'status' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::in(['draft', 'collecting'])], 'note' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => [$this->isMethod('post') ? 'required' : 'sometimes', 'uuid']];
        }

        return [];
    }
}
