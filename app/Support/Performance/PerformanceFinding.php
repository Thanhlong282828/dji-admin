<?php

namespace App\Support\Performance;

final readonly class PerformanceFinding
{
    public function __construct(
        public string $key,
        public string $severity,
        public string $component,
        public string $category,
        public string $description,
        public string $evidence,
        public string $recommendation,
        public string $status,
    ) {}

    /** @return array{key:string,severity:string,component:string,category:string,description:string,evidence:string,recommendation:string,status:string} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
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
