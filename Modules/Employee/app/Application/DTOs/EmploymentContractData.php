<?php

namespace Modules\Employee\Application\DTOs;

use Carbon\CarbonImmutable;
use Modules\Employee\Domain\Enums\ContractType;

final readonly class EmploymentContractData
{
    public function __construct(
        public string $contractNumber,
        public ContractType $type,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public string $baseSalary,
        public string $currency,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            self::text($data, 'contract_number'),
            ContractType::from(self::text($data, 'type')),
            CarbonImmutable::parse(self::text($data, 'starts_at')),
            is_string($data['ends_at'] ?? null) ? CarbonImmutable::parse($data['ends_at']) : null,
            number_format(self::number($data, 'base_salary'), 2, '.', ''),
            strtoupper(self::text($data, 'currency')),
        );
    }

    /** @return array{contract_number:string,type:string,starts_at:string,ends_at:string|null,base_salary:string,currency:string,terms:array<string, never>,status:string} */
    public function toArray(): array
    {
        return ['contract_number' => $this->contractNumber, 'type' => $this->type->value, 'starts_at' => $this->startsAt->toDateString(), 'ends_at' => $this->endsAt?->toDateString(), 'base_salary' => $this->baseSalary, 'currency' => $this->currency, 'terms' => [], 'status' => 'draft'];
    }

    /** @param array<string, mixed> $data */
    private static function text(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : '';
    }

    /** @param array<string, mixed> $data */
    private static function number(array $data, string $key): float
    {
        return is_numeric($data[$key] ?? null) ? (float) $data[$key] : 0.0;
    }
}
