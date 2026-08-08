<?php

namespace Modules\Integration\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['sometimes', 'integer', 'min:1'], 'code' => ['sometimes', 'string', 'max:64'], 'name' => ['sometimes', 'string', 'max:191'], 'type' => ['sometimes', 'in:http_api,file_export'], 'base_url' => ['nullable', 'url', 'max:500'], 'credential_reference' => ['nullable', 'string', 'max:1000'], 'connection_id' => ['sometimes', 'integer', 'min:1'], 'source_type' => ['sometimes', 'string', 'max:80'], 'source_id' => ['nullable', 'integer'], 'message_type' => ['sometimes', 'string', 'max:80'], 'payload' => ['sometimes', 'array'], 'idempotency_key' => ['sometimes', 'uuid']];
    }

    public function companyId(): int
    {
        return $this->integerValue('company_id');
    }

    public function integerValue(string $key): int
    {
        $value = $this->validated($key);

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    public function nullableInteger(string $key): ?int
    {
        $value = $this->validated($key);

        return $value === null ? null : (is_int($value) ? $value : (is_numeric($value) ? (int) $value : null));
    }

    public function stringValue(string $key): string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : '';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        $value = $this->validated('payload');
        if (! is_array($value)) {
            return [];
        }$result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
