<?php

namespace Modules\Audit\Domain\Services;

final class AuditRedactionService
{
    private const RESTRICTED = ['password', 'token', 'secret', 'private_key', 'bank_account', 'account_number', 'tax_number', 'salary', 'credential', 'storage_path'];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redact(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            $lower = strtolower($key);
            $restricted = array_any(self::RESTRICTED, fn (string $needle): bool => str_contains($lower, $needle));
            $result[$key] = $restricted ? '[REDACTED]' : (is_array($value) ? $this->redactNested($value) : $value);
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function redactNested(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && array_any(self::RESTRICTED, fn (string $n): bool => str_contains(strtolower($key), $n))) {
                $result[$key] = '[REDACTED]';
            } else {
                $result[$key] = is_array($value) ? $this->redactNested($value) : $value;
            }
        }

        return $result;
    }
}
