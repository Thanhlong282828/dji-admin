<?php

namespace App\Support\Security;

use InvalidArgumentException;

final class SafePathPolicy
{
    public function assertSafeFilename(string $filename): void
    {
        $decoded = rawurldecode($filename);
        if ($decoded === '' || str_contains($decoded, "\0") || str_contains($decoded, '/') || str_contains($decoded, '\\') || $decoded === '.' || $decoded === '..') {
            throw new InvalidArgumentException('Unsafe file name.');
        }
    }
}
