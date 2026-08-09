<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(
            'analytics_calculation_runs',
            ['company_id', 'period', 'idempotency_key'],
            true,
            'analytics_run_company_period_key_unique',
        );
        $this->addIndexIfMissing(
            'analytics_calculation_runs',
            ['company_id', 'period', 'status'],
            false,
            'analytics_run_company_period_status_idx',
        );
        $this->addIndexIfMissing(
            'career_history',
            ['company_id', 'aggregate_type', 'aggregate_id', 'created_at'],
            false,
            'career_history_aggregate_timeline_idx',
        );
        $this->addIndexIfMissing(
            'employee_leave_policy_assignments',
            ['company_id', 'employee_id', 'effective_from'],
            false,
            'leave_policy_assignment_effective_idx',
        );
        $this->addIndexIfMissing(
            'payroll_input_validation_issues',
            ['company_id', 'payroll_input_snapshot_id', 'is_blocking'],
            false,
            'payroll_input_issue_scope_idx',
        );
    }

    public function down(): void
    {
        // Canonical index repairs are intentionally retained during rollback.
    }

    /** @param list<string> $columns */
    private function addIndexIfMissing(string $table, array $columns, bool $unique, string $name): void
    {
        if (! DB::connection()->pretending() && (! Schema::hasTable($table) || $this->hasLogicalIndex($table, $columns, $unique))) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($columns, $unique, $name): void {
            if ($unique) {
                $blueprint->unique($columns, $name);

                return;
            }

            $blueprint->index($columns, $name);
        });
    }

    /** @param list<string> $columns */
    private function hasLogicalIndex(string $table, array $columns, bool $unique): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'] ?? null) === $columns && ($index['unique'] ?? null) === $unique) {
                return true;
            }
        }

        return false;
    }
};
