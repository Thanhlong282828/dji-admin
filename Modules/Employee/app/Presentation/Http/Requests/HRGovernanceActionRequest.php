<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class HRGovernanceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'reason' => ['nullable', 'string', 'max:1000'], 'action' => ['nullable', 'in:review,resolve'], 'retention_date' => ['nullable', 'date'], 'idempotency_key' => ['required', 'string', 'max:128']];
    }

    public function idempotencyKey(): string
    {
        $value = $this->validated('idempotency_key');
        abort_unless(is_string($value), 422);

        return $value;
    }

    public function action(): string
    {
        $value = $this->validated('action');

        return is_string($value) ? $value : 'review';
    }

    public function reason(string $default): string
    {
        $value = $this->validated('reason');

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
