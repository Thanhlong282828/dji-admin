<?php

namespace App\Support\Secrets;

final readonly class ResolvedSecret
{
    public function __construct(private string $value) {}

    public function revealAtInfrastructureBoundary(): string
    {
        return $this->value;
    }
}
