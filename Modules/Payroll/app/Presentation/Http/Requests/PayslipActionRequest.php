<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PayslipActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        if ($this->routeIs('admin.payroll.payslips.revoke')) {
            return ['reason' => ['required', 'string', 'max:1000']];
        }if ($this->routeIs('admin.payroll.payslips.generate-item', 'admin.payroll.payslips.generate-run')) {
            return ['idempotency_key' => ['required', 'uuid']];
        }

        return [];
    }
}
