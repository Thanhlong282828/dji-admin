<?php

namespace Modules\Employee\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class DataConsentData
{
    public function __construct(public string $purpose, public string $policyVersion, public bool $granted, public CarbonImmutable $decidedAt, public ?CarbonImmutable $expiresAt, public ?string $reason) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data, bool $granted): self
    {
        $purpose = is_string($data['purpose'] ?? null) ? $data['purpose'] : '';
        $version = is_string($data['policy_version'] ?? null) ? $data['policy_version'] : '';
        $expires = is_string($data['expires_at'] ?? null) ? CarbonImmutable::parse($data['expires_at']) : null;
        $reason = is_string($data['reason'] ?? null) ? $data['reason'] : null;

        return new self($purpose, $version, $granted, CarbonImmutable::now(), $expires, $reason);
    }

    /** @return array{purpose:string,policy_version:string,granted:bool,decided_at:CarbonImmutable,expires_at:CarbonImmutable|null,reason:string|null} */
    public function toArray(): array
    {
        return ['purpose' => $this->purpose, 'policy_version' => $this->policyVersion, 'granted' => $this->granted, 'decided_at' => $this->decidedAt, 'expires_at' => $this->expiresAt, 'reason' => $this->reason];
    }
}
