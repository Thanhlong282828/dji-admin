<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_branch_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('assignment_type', 20)->default('primary');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source', 30)->default('branch_access');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'assignment_type', 'effective_from'], 'employee_branch_effective_lookup');
        });

        Schema::table('attendance_events', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'branch_id', 'local_date'], 'attendance_event_branch_date');
        });
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'branch_id', 'work_date'], 'attendance_record_branch_date');
        });
        Schema::table('payroll_employee_inputs', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->string('branch_snapshot')->nullable()->after('branch_id');
            $table->index(['company_id', 'branch_id', 'payroll_input_snapshot_id'], 'payroll_input_branch_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_employee_inputs', function (Blueprint $table): void {
            $table->dropIndex('payroll_input_branch_snapshot');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('branch_snapshot');
        });
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropIndex('attendance_record_branch_date');
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::table('attendance_events', function (Blueprint $table): void {
            $table->dropIndex('attendance_event_branch_date');
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::dropIfExists('employee_branch_assignments');
    }
};
