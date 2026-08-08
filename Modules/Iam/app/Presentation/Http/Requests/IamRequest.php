<?php

namespace Modules\Iam\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'subject_user_id' => ['sometimes', 'integer', 'exists:users,id'], 'reason' => ['sometimes', 'required', 'string', 'max:2000'], 'idempotency_key' => ['sometimes', 'uuid'], 'workflow_definition_id' => ['nullable', 'integer', 'exists:workflow_definitions,id'], 'items' => ['sometimes', 'array', 'min:1'], 'items.*.type' => ['required_with:items', 'in:role,permission'], 'items.*.id' => ['required_with:items', 'integer', 'min:1'], 'items.*.effective_to' => ['nullable', 'date', 'after:now'], 'name' => ['sometimes', 'string', 'max:255'], 'scope_type' => ['sometimes', 'in:company,user,role,privileged'], 'scope_id' => ['nullable', 'integer'], 'due_date' => ['sometimes', 'date', 'after_or_equal:today'], 'decision' => ['sometimes', 'in:keep,revoke,change'], 'version' => ['sometimes', 'integer', 'min:1']];
    }

    public function companyId(): int
    {
        return $this->integer('company_id');
    }

    public function subjectUserId(): int
    {
        return $this->integer('subject_user_id');
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }

    public function idempotencyKey(): string
    {
        return $this->string('idempotency_key')->toString();
    }

    public function text(string $key): string
    {
        return $this->string($key)->toString();
    }

    public function nullableInteger(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }

    /** @return list<array{type:string,id:int,effective_to:string|null}> */
    public function entitlementItems(): array
    {
        $raw = $this->input('items', []);
        if (! is_array($raw)) {
            return [];
        }
        $items = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $type = $item['type'] ?? null;
            $id = $item['id'] ?? null;
            $expiry = $item['effective_to'] ?? null;
            if (! is_string($type) || (! is_int($id) && ! (is_string($id) && ctype_digit($id))) || (! is_null($expiry) && ! is_string($expiry))) {
                continue;
            }
            $items[] = ['type' => $type, 'id' => (int) $id, 'effective_to' => $expiry];
        }

        return $items;
    }
}
