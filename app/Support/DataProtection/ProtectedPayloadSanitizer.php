<?php

namespace App\Support\DataProtection;

final class ProtectedPayloadSanitizer
{
    public function __construct(private DataFieldRegistry $registry) {}

    /** @param array<array-key, mixed> $payload
     * @return array<array-key, mixed>
     */
    public function sanitize(array $payload): array
    {
        $safe = [];
        foreach ($payload as $key => $value) {
            $definition = is_string($key) ? $this->registry->find($key) : null;
            if ($definition !== null) {
                $safe[$key] = ['changed' => true, 'classification' => $definition->classification->value, 'value' => '[REDACTED]'];
            } else {
                $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
            }
        }

        return $safe;
    }
}
