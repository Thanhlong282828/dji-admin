<?php

namespace App\Support\Security;

final readonly class SecurityFinding
{
    public function __construct(
        public string $key,
        public SecuritySeverity $severity,
        public string $category,
        public string $component,
        public string $description,
        public string $evidence,
        public string $status,
        public string $remediation,
    ) {}

    /** @return array{key:string,severity:string,category:string,component:string,description:string,evidence:string,status:string,remediation:string} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'severity' => $this->severity->value, 'category' => $this->category, 'component' => $this->component, 'description' => $this->description, 'evidence' => $this->evidence, 'status' => $this->status, 'remediation' => $this->remediation];
    }
}
