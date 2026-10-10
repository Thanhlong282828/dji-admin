<?php

namespace Modules\Finance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionTreasuryInternalTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $action = $this->route('action');

        return is_string($action) && $this->user()?->can('treasury-internal-transfer.'.$action) === true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1']];
    }
}
