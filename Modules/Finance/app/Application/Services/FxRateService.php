<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Enums\SupportedCurrency;
use Modules\Finance\Domain\Enums\FxRateType;
use stdClass;

final readonly class FxRateService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        $base = $this->currency($data['base_currency'] ?? null);
        $quote = $this->currency($data['quote_currency'] ?? null);
        if ($base === $quote) {
            throw ValidationException::withMessages(['quote_currency' => 'FX_RATE_INVALID_PAIR']);
        }
        $rate = $this->rate($data['rate_decimal'] ?? null);
        $type = FxRateType::tryFrom($this->text($data['rate_type'] ?? null))?->value
            ?? throw ValidationException::withMessages(['rate_type' => 'FX_RATE_TYPE_UNSUPPORTED']);
        $from = CarbonImmutable::parse($this->text($data['effective_from'] ?? null))->toDateString();
        $to = isset($data['effective_to']) && $data['effective_to'] !== '' ? CarbonImmutable::parse($this->text($data['effective_to']))->toDateString() : null;
        $rateDate = CarbonImmutable::parse($this->text($data['rate_date'] ?? null))->toDateString();
        $sourceReference = $this->text($data['source_reference'] ?? null);
        $idempotencyKey = $this->text($data['idempotency_key'] ?? null);
        if ($to !== null && $to < $from) {
            throw ValidationException::withMessages(['effective_to' => 'FX_RATE_INVALID_EFFECTIVE_RANGE']);
        }

        return $this->db->transaction(function () use ($companyId, $actor, $base, $quote, $rate, $type, $from, $to, $rateDate, $sourceReference, $idempotencyKey): int {
            $company = $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->value('id');
            if (! is_numeric($company)) {
                throw ValidationException::withMessages(['company' => 'FX_RATE_COMPANY_NOT_FOUND']);
            }
            $duplicate = $this->db->table('finance_fx_rates')->where(['company_id' => $companyId, 'idempotency_key' => $idempotencyKey])->lockForUpdate()->first();
            if ($duplicate instanceof stdClass) {
                $expected = [$base, $quote, $rate, $rateDate, $type, $sourceReference, $from, $to];
                $actual = [(string) $duplicate->base_currency, (string) $duplicate->quote_currency, (string) $duplicate->rate_decimal, (string) $duplicate->rate_date, (string) $duplicate->rate_type, (string) $duplicate->source_reference, (string) $duplicate->effective_from, $duplicate->effective_to === null ? null : (string) $duplicate->effective_to];
                if ($expected !== $actual) {
                    throw ValidationException::withMessages(['idempotency_key' => 'FX_RATE_IDEMPOTENCY_CONFLICT']);
                }

                return (int) $duplicate->id;
            }
            $version = (int) $this->db->table('finance_fx_rates')->where(['company_id' => $companyId, 'base_currency' => $base, 'quote_currency' => $quote, 'rate_type' => $type])->max('version') + 1;

            return (int) $this->db->table('finance_fx_rates')->insertGetId([
                'company_id' => $companyId, 'base_currency' => $base, 'quote_currency' => $quote,
                'rate_decimal' => $rate, 'rate_date' => $rateDate,
                'rate_type' => $type, 'source_reference' => $sourceReference,
                'version' => $version, 'status' => 'draft', 'effective_from' => $from, 'effective_to' => $to,
                'idempotency_key' => $idempotencyKey, 'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function transition(int $companyId, int $rateId, string $action, User $actor): void
    {
        $this->db->transaction(function () use ($companyId, $rateId, $action, $actor): void {
            // Activation is a scope-wide invariant: two different rates can overlap even
            // though each worker locks a different rate row. Lock the tenant aggregate
            // before either rate row so every activation uses one deterministic order.
            if ($action === 'activate') {
                $company = $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->value('id');
                if (! is_numeric($company)) {
                    throw ValidationException::withMessages(['company' => 'FX_RATE_COMPANY_NOT_FOUND']);
                }
            }
            $row = $this->db->table('finance_fx_rates')->where(['company_id' => $companyId, 'id' => $rateId])->lockForUpdate()->first();
            if (! $row instanceof stdClass) {
                throw ValidationException::withMessages(['rate' => 'FX_RATE_NOT_FOUND']);
            }
            $status = $this->text($row->status ?? null);
            [$required, $next] = match ($action) {
                'submit' => ['draft', 'submitted'], 'approve' => ['submitted', 'approved'],
                'activate' => ['approved', 'active'], 'retire' => ['active', 'retired'],
                default => throw ValidationException::withMessages(['action' => 'FX_RATE_INVALID_TRANSITION']),
            };
            if ($status !== $required) {
                throw ValidationException::withMessages(['status' => 'FX_RATE_INVALID_TRANSITION']);
            }
            if (in_array($action, ['approve', 'activate'], true) && (int) $row->created_by === (int) $actor->id) {
                throw ValidationException::withMessages(['actor' => 'FX_RATE_MAKER_CHECKER_REQUIRED']);
            }
            if ($action === 'activate') {
                $overlap = $this->db->table('finance_fx_rates')->where('company_id', $companyId)->where('id', '<>', $rateId)
                    ->where(['base_currency' => $row->base_currency, 'quote_currency' => $row->quote_currency, 'rate_type' => $row->rate_type, 'status' => 'active'])
                    ->where('effective_from', '<=', $row->effective_to ?? '9999-12-31')
                    ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $row->effective_from))->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['effective_from' => 'FX_RATE_ACTIVE_WINDOW_OVERLAP']);
                }
            }
            $fields = ['status' => $next, 'updated_at' => now()];
            if ($action !== 'retire') {
                $prefix = ['submit' => 'submitted', 'approve' => 'approved', 'activate' => 'activated'][$action];
                $fields[$prefix.'_by'] = $actor->id;
                $fields[$prefix.'_at'] = now();
            } else {
                $fields['retired_by'] = $actor->id;
                $fields['retired_at'] = now();
            }
            $this->db->table('finance_fx_rates')->where('id', $rateId)->update($fields);
        });
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper($this->text($value));
        if (! in_array($currency, SupportedCurrency::values(), true)) {
            throw ValidationException::withMessages(['currency' => 'FX_RATE_INVALID_CURRENCY']);
        }

        return $currency;
    }

    private function rate(mixed $value): string
    {
        $rate = $this->text($value);
        if (! preg_match('/^\d+(?:\.\d{1,12})?$/', $rate) || bccomp($rate, '0', 12) !== 1) {
            throw ValidationException::withMessages(['rate_decimal' => 'FX_RATE_INVALID_RATE']);
        }

        return $rate;
    }

    private function text(mixed $value): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : throw ValidationException::withMessages(['value' => 'FX_RATE_REQUIRED']);
    }
}
