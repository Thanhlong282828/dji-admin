<?php

namespace App\Support;

final class SensitiveDataRedactor
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'authorization', 'cookie', 'session_id',
        'token', 'access_token', 'refresh_token', 'api_key', 'secret', 'credential',
        'bank_account', 'bank_account_number', 'tax_number', 'salary', 'private_path',
    ];

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function redact(array $payload): array
    {
        $safe = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->sensitive($key)) {
                $safe[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $safe[$key] = $this->redact($value);
            } else {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    private function sensitive(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($normalized === $sensitive || str_ends_with($normalized, '_'.$sensitive)) {
                return true;
            }
        }

        return false;
    }
}
