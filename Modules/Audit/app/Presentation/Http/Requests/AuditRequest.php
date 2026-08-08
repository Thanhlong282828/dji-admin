<?php

namespace Modules\Audit\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'format' => ['sometimes', 'string', 'in:csv,pdf'],
            'name' => ['sometimes', 'string', 'max:120'],
            'retention_days' => ['sometimes', 'integer', 'min:30', 'max:36500'],
            'range_start' => ['sometimes', 'date'],
            'range_end' => ['sometimes', 'date', 'after_or_equal:range_start'],
        ];
    }

    public function companyId(): int
    {
        return $this->integer('company_id');
    }

    public function search(): ?string
    {
        $value = $this->validated('search');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function exportFormat(): string
    {
        $value = $this->validated('format');

        return is_string($value) ? $value : 'csv';
    }

    public function policyName(): string
    {
        $value = $this->validated('name');

        return is_string($value) ? $value : 'Enterprise default';
    }

    public function retentionDays(): int
    {
        return $this->integer('retention_days', 2555);
    }

    public function rangeStart(): string
    {
        $value = $this->validated('range_start');

        return is_string($value) ? $value : today()->subYear()->toDateString();
    }

    public function rangeEnd(): string
    {
        $value = $this->validated('range_end');

        return is_string($value) ? $value : today()->toDateString();
    }
}
