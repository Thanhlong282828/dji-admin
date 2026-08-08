<?php

namespace Modules\Employee\Domain\Governance;

use Illuminate\Support\Facades\DB;

final class MissingDocumentRule implements HRDataQualityRule
{
    public function code(): string
    {
        return 'missing_document';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function evaluate(int $c): array
    {
        $rows = DB::table('employees as e')->leftJoin('employee_documents as d', 'd.employee_id', '=', 'e.id')->where('e.company_id', $c)->whereNull('e.deleted_at')->whereNull('d.id')->select('e.id')->get();

        return array_values($rows->map(fn (object $r): array => ['entity_type' => 'employee', 'entity_id' => is_numeric($r->id ?? null) ? (int) $r->id : 0, 'message' => 'Nhân viên chưa có tài liệu hồ sơ.'])->all());
    }
}
