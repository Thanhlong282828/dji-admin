<?php

namespace App\Support\Architecture;

use InvalidArgumentException;

final readonly class ArchitectureFinding
{
    public function __construct(
        public string $id,
        public string $severity,
        public string $component,
        public string $category,
        public string $description,
        public string $evidence,
        public string $recommendation,
        public string $status = 'open',
    ) {
        if (! in_array($severity, ['critical', 'high', 'medium', 'low'], true)) {
            throw new InvalidArgumentException('Unsupported architecture finding severity.');
        }
    }

    /** @return array{id:string,severity:string,component:string,category:string,description:string,evidence:string,recommendation:string,status:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'severity' => $this->severity,
            'component' => $this->component,
            'category' => $this->category,
            'description' => $this->description,
            'evidence' => $this->evidence,
            'recommendation' => $this->recommendation,
            'status' => $this->status,
        ];
    }
}
