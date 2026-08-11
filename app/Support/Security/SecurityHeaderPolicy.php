<?php

namespace App\Support\Security;

final class SecurityHeaderPolicy
{
    /** @return array<string, string> */
    public function required(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
    }

    /** @param array<string, string> $headers */
    public function compliant(array $headers): bool
    {
        foreach ($this->required() as $name => $expected) {
            if (($headers[$name] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
    }
}
