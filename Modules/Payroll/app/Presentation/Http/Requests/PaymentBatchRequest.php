<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PaymentBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        if ($this->routeIs('admin.payroll.payment-batches.store')) {
            return ['company_id' => ['required', 'integer', 'min:1'], 'run_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']];
        }if ($this->routeIs('admin.payroll.payment-batches.reject')) {
            return ['company_id' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:1000']];
        }

        return ['company_id' => ['required', 'integer', 'min:1']];
    }
}
