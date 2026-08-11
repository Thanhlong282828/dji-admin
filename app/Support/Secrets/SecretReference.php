<?php

namespace App\Support\Secrets;

use InvalidArgumentException;

final readonly class SecretReference
{
    public function __construct(public string $value)
    {
        if (preg_match('#^secret://[a-z0-9][a-z0-9._-]{1,63}/[a-z0-9][a-z0-9._/-]{1,127}$#', $value) !== 1 || str_contains($value, '..')) {
            throw new InvalidArgumentException('Invalid secret reference.');
        }
    }
}
