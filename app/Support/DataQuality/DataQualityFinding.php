<?php

namespace App\Support\DataQuality;

final readonly class DataQualityFinding
{
    public function __construct(
        public string $key,
        public string $component,
        public string $severity,
        public string $description,
        public int $count,
    ) {}

    /** @return array{key:string,component:string,severity:string,description:string,count:int,status:string} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'component' => $this->component,
            'severity' => $this->severity,
            'description' => $this->description,
            'count' => $this->count,
            'status' => $this->count === 0 ? 'passed' : 'failed',
        ];
    }
}
