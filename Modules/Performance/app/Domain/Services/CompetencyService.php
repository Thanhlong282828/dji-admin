<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;

final class CompetencyService
{
    /** @return list<array<string,mixed>> */
    public function profile(int $companyId, int $employeeId): array
    {
        $rows = [];
        foreach (DB::table('employee_competencies as ec')->join('competencies as c', 'c.id', '=', 'ec.competency_id')->where('ec.company_id', $companyId)->where('ec.employee_id', $employeeId)->orderByDesc('ec.effective_date')->select(['c.code', 'c.name', 'ec.level', 'ec.effective_date'])->get() as $r) {
            $values = get_object_vars($r);
            $rows[] = [
                'code' => is_string($values['code'] ?? null) ? $values['code'] : '',
                'name' => is_string($values['name'] ?? null) ? $values['name'] : '',
                'level' => is_int($values['level'] ?? null) ? $values['level'] : 0,
                'effective_date' => is_string($values['effective_date'] ?? null) ? $values['effective_date'] : '',
            ];
        }

        return $rows;
    }
}
