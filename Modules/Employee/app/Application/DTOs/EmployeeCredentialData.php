<?php

namespace Modules\Employee\Application\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use Modules\Employee\Domain\Enums\CredentialType;

final readonly class EmployeeCredentialData
{
    public function __construct(
        public CredentialType $type,
        public string $name,
        public ?string $issuer,
        public ?CarbonImmutable $issuedAt,
        public ?CarbonImmutable $expiresAt,
        public ?string $credentialNumber,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = is_string($data['type'] ?? null) ? $data['type'] : '';
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $issuer = is_string($data['issuer'] ?? null) ? $data['issuer'] : null;
        $issued = is_string($data['issued_at'] ?? null) ? CarbonImmutable::parse($data['issued_at']) : null;
        $expires = is_string($data['expires_at'] ?? null) ? CarbonImmutable::parse($data['expires_at']) : null;
        $number = is_string($data['credential_number'] ?? null) ? $data['credential_number'] : null;

        return new self(CredentialType::from($type), $name, $issuer, $issued, $expires, $number);
    }

    /** @return array{type:string,name:string,issuer:string|null,issued_at:string|null,expires_at:string|null,credential_number:string|null} */
    public function toPersistence(Encrypter $encrypter): array
    {
        return [
            'type' => $this->type->value,
            'name' => $this->name,
            'issuer' => $this->issuer,
            'issued_at' => $this->issuedAt?->toDateString(),
            'expires_at' => $this->expiresAt?->toDateString(),
            'credential_number' => $this->credentialNumber === null ? null : $encrypter->encryptString($this->credentialNumber),
        ];
    }
}
