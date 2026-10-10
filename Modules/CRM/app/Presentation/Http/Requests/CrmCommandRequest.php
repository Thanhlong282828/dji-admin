<?php

namespace Modules\CRM\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CrmCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') === true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $action = (string) $this->route('action');

        return [
            'company_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'id' => [in_array($action, ['lead-status', 'lead-convert', 'opportunity-stage'], true) ? 'required' : 'nullable', 'integer', 'min:1'],
            'name' => [$action === 'lead-create' ? 'required' : 'nullable', 'string', 'max:255'],
            'contact' => [$action === 'lead-create' ? 'required' : 'nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => [$action === 'lead-status' ? 'required' : 'nullable', 'in:new,contacted,qualified,lost'],
            'stage' => [$action === 'opportunity-stage' ? 'required' : 'nullable', 'in:discovery,qualification,proposal,negotiation,won,lost'],
            'lead_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'], 'expected_value_minor' => ['nullable', 'integer', 'min:0'],
            'probability' => ['nullable', 'integer', 'between:0,100'], 'expected_close_date' => ['nullable', 'date'],
        ];
    }

    /** @return array<string,mixed> */
    public function commandData(string $action): array
    {
        abort_unless(in_array($action, ['lead-create', 'lead-status', 'lead-convert', 'opportunity-create', 'opportunity-stage'], true), 404);

        return $this->validated();
    }
}
