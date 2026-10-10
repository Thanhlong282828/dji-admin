<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

final readonly class FxRateResolver
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function resolve(int $companyId, string $base, string $quote, string $date, string $type): array
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);
        if ($base === $quote) {
            return ['identity' => true, 'id' => null, 'company_id' => $companyId, 'base_currency' => $base, 'quote_currency' => $quote, 'rate_decimal' => '1.000000000000', 'rate_date' => $date, 'rate_type' => 'identity', 'version' => 1, 'source_reference' => 'SAME_CURRENCY_IDENTITY'];
        }
        $rows = $this->db->table('finance_fx_rates')->where(['company_id' => $companyId, 'base_currency' => $base, 'quote_currency' => $quote, 'rate_type' => $type, 'status' => 'active'])
            ->where('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))->orderByDesc('version')->limit(2)->get();
        if ($rows->count() !== 1) {
            throw ValidationException::withMessages(['rate' => 'FX_RATE_NOT_AVAILABLE']);
        }

        return get_object_vars($rows->first());
    }
}
