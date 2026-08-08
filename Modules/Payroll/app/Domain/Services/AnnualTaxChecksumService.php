<?php

namespace Modules\Payroll\Domain\Services;

use JsonException;
use Modules\Payroll\Application\DTOs\AnnualTaxSourceData;

final readonly class AnnualTaxChecksumService
{
    public const VERSION = 'annual-tax-source-v1';

    public function source(AnnualTaxSourceData $source): string
    {
        return hash('sha256', $this->encode($source->checksumPayload()));
    }

    /** @param list<AnnualTaxSourceData> $sources */
    public function reconciliation(int $companyId, int $employeeId, int $taxYear, string $jurisdiction, string $currency, int $version, array $sources): string
    {
        usort($sources, static fn (AnnualTaxSourceData $a, AnnualTaxSourceData $b): int => [$a->sortKey, $a->reference] <=> [$b->sortKey, $b->reference]);

        return hash('sha256', $this->encode(['checksum_version' => self::VERSION, 'company_id' => $companyId, 'employee_id' => $employeeId, 'tax_year' => $taxYear, 'jurisdiction' => $jurisdiction, 'currency' => $currency, 'reconciliation_version' => $version, 'sources' => array_map(fn (AnnualTaxSourceData $source): array => ['sort_key' => $source->sortKey, 'reference' => $source->reference, 'checksum' => $this->source($source)], $sources)]));
    }

    /** @param array<string, mixed> $payload */
    public function hashPayload(array $payload): string
    {
        return hash('sha256', $this->encode($payload));
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        try {
            return json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw new JsonException('Unable to encode canonical Annual Tax source.', previous: $exception);
        }
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }
}
