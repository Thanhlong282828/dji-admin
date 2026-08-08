<?php

namespace Modules\Workflow\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'code' => ['nullable', 'string', 'max:100'], 'name' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'routing_type' => ['nullable', Rule::in(['sequential', 'parallel', 'any_one', 'all_must_approve', 'conditional'])], 'steps' => ['nullable', 'array', 'min:1'], 'steps.*.key' => ['required_with:steps', 'string', 'max:100'], 'steps.*.name' => ['required_with:steps', 'string', 'max:255'], 'steps.*.mode' => ['required_with:steps', Rule::in(['sequential', 'parallel', 'any_one', 'all_must_approve', 'conditional'])], 'steps.*.resolver_type' => ['required_with:steps', Rule::in(['specific_user', 'manager', 'permission', 'organization_role'])], 'steps.*.resolver_value' => ['required_with:steps', 'string', 'max:255'], 'definition_id' => ['nullable', 'integer', 'min:1'], 'subject_type' => ['nullable', 'string', 'max:100'], 'subject_id' => ['nullable', 'integer', 'min:1'], 'idempotency_key' => ['nullable', 'string', 'max:128'], 'version' => ['nullable', 'integer', 'min:1'], 'decision' => ['nullable', Rule::in(['approve', 'reject'])], 'reason' => ['nullable', 'string', 'max:2000']];
    }

    public function companyId(): int
    {
        return $this->integer('company_id');
    }

    public function actorId(): int
    {
        $id = $this->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : 0;
    }

    public function text(string $key, string $default = ''): string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : $default;
    }

    public function number(string $key): int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    /** @return list<array{key:string,name:string,mode:string,resolver_type:string,resolver_value:string,condition?:array<string,scalar|null>,due_after_minutes?:int|null,escalation_after_minutes?:int|null,fallback_resolver_type?:string|null,fallback_resolver_value?:string|null}> */
    public function steps(): array
    {
        $raw = $this->validated('steps');
        if (! is_array($raw)) {
            return [];
        } $out = [];
        foreach ($raw as $step) {
            if (! is_array($step)) {
                continue;
            }
            $out[] = [
                'key' => $this->arrayString($step, 'key'),
                'name' => $this->arrayString($step, 'name'),
                'mode' => $this->arrayString($step, 'mode', 'sequential'),
                'resolver_type' => $this->arrayString($step, 'resolver_type'),
                'resolver_value' => $this->arrayString($step, 'resolver_value'),
                'due_after_minutes' => $this->arrayNullableInt($step, 'due_after_minutes'),
                'escalation_after_minutes' => $this->arrayNullableInt($step, 'escalation_after_minutes'),
            ];
        }

        return $out;
    }

    /** @param array<array-key, mixed> $source */
    private function arrayString(array $source, string $key, string $default = ''): string
    {
        $value = $source[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /** @param array<array-key, mixed> $source */
    private function arrayNullableInt(array $source, string $key): ?int
    {
        $value = $source[$key] ?? null;

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : null);
    }
}
