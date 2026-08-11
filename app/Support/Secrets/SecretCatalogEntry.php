<?php

namespace App\Support\Secrets;

final readonly class SecretCatalogEntry
{
    public function __construct(public SecretReference $reference, public string $component, public string $classification, public string $source, public bool $required, public int $rotationDays, public string $status) {}

    /** @return array{reference:string,component:string,classification:string,source:string,required:bool,rotation_days:int,status:string,rotation_due:string,expires:string,last_verified:string} */
    public function toArray(): array
    {
        return ['reference' => $this->reference->value, 'component' => $this->component, 'classification' => $this->classification, 'source' => $this->source, 'required' => $this->required, 'rotation_days' => $this->rotationDays, 'status' => $this->status, 'rotation_due' => 'metadata unavailable', 'expires' => 'provider managed', 'last_verified' => 'current request'];
    }
}
