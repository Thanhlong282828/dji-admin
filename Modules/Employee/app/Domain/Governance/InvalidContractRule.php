<?php

namespace Modules\Employee\Domain\Governance;

use Illuminate\Support\Facades\DB;

final class InvalidContractRule implements HRDataQualityRule
{
    public function code(): string
    {
        return 'invalid_contract';
    }

    public function severity(): string
    {
        return 'blocking';
    }

    public function evaluate(int $c): array
    {
        $rows = DB::table('employment_contracts as ct')->join('employees as e', 'e.id', '=', 'ct.employee_id')->where('e.company_id', $c)->whereNotNull('ct.ends_at')->whereColumn('ct.ends_at', '<', 'ct.starts_at')->select('ct.id')->get();

        return array_values($rows->map(fn (object $r): array => ['entity_type' => 'employment_contract', 'entity_id' => is_numeric($r->id ?? null) ? (int) $r->id : 0, 'message' => 'Ngày kết thúc hợp đồng nhỏ hơn ngày bắt đầu.'])->all());
    }
}
