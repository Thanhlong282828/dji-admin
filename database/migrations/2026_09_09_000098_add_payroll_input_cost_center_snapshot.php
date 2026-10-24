<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_employee_inputs', function (Blueprint $table): void {
            $table->foreignId('cost_center_id')->nullable()->after('organization_unit_snapshot')->constrained()->restrictOnDelete();
            $table->string('cost_center_code_snapshot')->nullable()->after('cost_center_id');
            $table->string('cost_center_name_snapshot')->nullable()->after('cost_center_code_snapshot');
            $table->index(['company_id', 'cost_center_id', 'payroll_input_snapshot_id'], 'payroll_input_cost_center_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_employee_inputs', function (Blueprint $table): void {
            $table->dropIndex('payroll_input_cost_center_idx');
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn(['cost_center_code_snapshot', 'cost_center_name_snapshot']);
        });
    }
};
