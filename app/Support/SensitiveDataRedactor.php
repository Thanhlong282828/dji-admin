<?php

namespace App\Support;

use App\Support\DataProtection\DataFieldRegistry;

final class SensitiveDataRedactor
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'authorization', 'cookie', 'session_id',
        'token', 'access_token', 'refresh_token', 'api_key', 'secret', 'credential',
        'api-key', 'private_key', 'access_key', 'client_secret', 'webhook_secret', 'encryption_key', 'signing_key',
        'bank_account', 'bank_account_number', 'tax_number', 'salary', 'private_path',
        'government_id', 'national_id', 'identity_document', 'document_storage_key',
        'insurance_number', 'medical_record', 'ciphertext',
    ];

    public function __construct(private ?DataFieldRegistry $registry = null) {}

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
        $normalized = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace('-', '_', $key)));
        if (($this->registry ?? new DataFieldRegistry)->find($normalized) !== null) {
            return true;
        }
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($normalized === $sensitive || str_ends_with($normalized, '_'.$sensitive)) {
                return true;
            }
        }

        return false;
    }
}
