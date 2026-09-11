<?php

namespace Modules\Commerce\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/** @phpstan-type CustomerMetric array{customer_id:int,name:string,first_purchase:?string,last_purchase:?string,days_since_last_purchase:?int,purchase_count:int,recognized_spend_minor:int,average_basket_minor:int,visit_count:int,preferred_branch_id:?int,segment:string} */
final readonly class CustomerMetricsService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return Collection<int,CustomerMetric> */
    public function forCompany(int $companyId, ?CarbonImmutable $asOf = null): Collection
    {
        $asOf ??= CarbonImmutable::now();
        $pos = $this->db->table('commerce_customers as c')
            ->leftJoin('customer_visits as v', function (JoinClause $join): void {
                $join->on('v.customer_id', '=', 'c.id')->where('v.status', '=', 'checked_out');
            })
            ->leftJoin('branch_pos_receipts as r', function (JoinClause $join): void {
                $join->on('r.customer_visit_id', '=', 'v.id')->where('r.status', '=', 'paid');
            })
            ->where('c.company_id', $companyId)
            ->groupBy('c.id', 'c.full_name', 'c.email', 'c.phone')
            ->selectRaw('c.id, c.full_name, c.email, c.phone, COUNT(DISTINCT v.id) visit_count, COUNT(DISTINCT r.id) pos_purchase_count, COALESCE(SUM(r.total_minor),0) pos_spend_minor, MIN(r.paid_at) pos_first_purchase, MAX(r.paid_at) pos_last_purchase, MAX(r.branch_id) preferred_branch_id')
            ->get();

        $marketplaceByEmail = $this->marketplaceByEmail($companyId);

        return $pos->map(function (object $customer) use ($marketplaceByEmail, $asOf): array {
            $marketplace = $marketplaceByEmail->get(mb_strtolower($this->string($customer, 'email')), ['count' => 0, 'spend' => 0, 'first' => null, 'last' => null]);
            $count = $this->integer($customer, 'pos_purchase_count') + $marketplace['count'];
            $spend = $this->integer($customer, 'pos_spend_minor') + $marketplace['spend'];
            $first = $this->earliest($this->nullableString($customer, 'pos_first_purchase'), $marketplace['first']);
            $last = $this->latest($this->nullableString($customer, 'pos_last_purchase'), $marketplace['last']);
            $days = $last === null ? null : (int) max(0, $last->startOfDay()->diffInDays($asOf->startOfDay()));

            return [
                'customer_id' => $this->integer($customer, 'id'),
                'name' => $this->string($customer, 'full_name'),
                'first_purchase' => $first?->toDateString(),
                'last_purchase' => $last?->toDateString(),
                'days_since_last_purchase' => $days,
                'purchase_count' => $count,
                'recognized_spend_minor' => $spend,
                'average_basket_minor' => $count === 0 ? 0 : intdiv($spend, $count),
                'visit_count' => $this->integer($customer, 'visit_count'),
                'preferred_branch_id' => $this->nullableInteger($customer, 'preferred_branch_id'),
                'segment' => $this->segment($count, $days),
            ];
        });
    }

    /** @return Collection<string,array{count:int,spend:int,first:?string,last:?string}> */
    private function marketplaceByEmail(int $companyId): Collection
    {
        return $this->db->table('orders as o')->join('users as u', 'u.id', '=', 'o.buyer_user_id')->join('stores as s', 's.id', '=', 'o.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $companyId)->where('o.payment_status', 'paid')->whereNotNull('u.email')->groupBy('u.email')->selectRaw('LOWER(u.email) email_key, COUNT(o.id) purchase_count, COALESCE(SUM(o.total_minor),0) spend_minor, MIN(o.paid_at) first_purchase, MAX(o.paid_at) last_purchase')->get()->mapWithKeys(fn (object $row): array => [$this->string($row, 'email_key') => ['count' => $this->integer($row, 'purchase_count'), 'spend' => $this->integer($row, 'spend_minor'), 'first' => $this->nullableString($row, 'first_purchase'), 'last' => $this->nullableString($row, 'last_purchase')]]);
    }

    private function segment(int $purchases, ?int $days): string
    {
        if ($purchases === 0 || $days === null || ($purchases === 1 && $days <= $this->configInteger('new_days', 30))) {
            return 'NEW';
        }
        if ($purchases >= 2 && $days <= $this->configInteger('repeat_days', 90)) {
            return 'REPEAT';
        }
        if ($days <= $this->configInteger('new_days', 30)) {
            return 'ACTIVE';
        }

        return $days <= $this->configInteger('at_risk_days', 180) ? 'AT_RISK' : 'DORMANT';
    }

    private function earliest(?string $a, ?string $b): ?CarbonImmutable
    {
        $dates = collect([$a, $b])->filter()->map(fn (string $value): CarbonImmutable => CarbonImmutable::parse($value));

        return $dates->sort()->first();
    }

    private function latest(?string $a, ?string $b): ?CarbonImmutable
    {
        $dates = collect([$a, $b])->filter()->map(fn (string $value): CarbonImmutable => CarbonImmutable::parse($value));

        return $dates->sortDesc()->first();
    }

    private function integer(object $row, string $field): int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableInteger(object $row, string $field): ?int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : null;
    }

    private function string(object $row, string $field): string
    {
        $value = data_get($row, $field);

        return is_string($value) ? $value : '';
    }

    private function nullableString(object $row, string $field): ?string
    {
        $value = data_get($row, $field);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function configInteger(string $key, int $fallback): int
    {
        $value = config('commerce.crm.'.$key);

        return is_int($value) ? $value : $fallback;
    }
}
