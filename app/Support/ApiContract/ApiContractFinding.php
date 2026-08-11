<?php

namespace App\Support\ApiContract;

use InvalidArgumentException;

final readonly class ApiContractFinding
{
    public function __construct(
        public string $key,
        public string $severity,
        public string $category,
        public string $description,
        public string $evidence,
    ) {
        if (! in_array($severity, ['info', 'warning', 'critical'], true)) {
            throw new InvalidArgumentException('Unsupported API contract finding severity.');
        }
    }

    /** @return array{key:string,severity:string,category:string,description:string,evidence:string} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'severity' => $this->severity, 'category' => $this->category, 'description' => $this->description, 'evidence' => $this->evidence];
    }
}
