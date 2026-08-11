<?php

namespace App\Support\ApiContract;

final class SafeExampleScanner
{
    /** @return list<string> */
    public function violations(mixed $value, string $path = '$'): array
    {
        if (is_array($value)) {
            $violations = [];
            foreach ($value as $key => $child) {
                array_push($violations, ...$this->violations($child, $path.'.'.(string) $key));
            }

            return $violations;
        }
        if (! is_string($value)) {
            return [];
        }
        $patterns = [
            '/BEGIN (?:RSA |EC )?PRIVATE KEY/i',
            '/(?:DB_PASSWORD|APP_KEY|Authorization)\s*[=:]\s*\S+/i',
            '/\b(?:sk_live|pk_live)_[A-Za-z0-9]+\b/',
            '/\bBearer\s+[A-Za-z0-9._-]{20,}\b/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return [$path];
            }
        }

        return [];
    }
}
