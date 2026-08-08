<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_components')) {
            Schema::create('salary_components', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('company_id')->constrained()->restrictOnDelete();
                $t->string('code', 50);
                $t->string('name');
                $t->text('description')->nullable();
                $t->string('category', 30);
                $t->string('calculation_type', 30);
                $t->string('value_type', 30);
                $t->bigInteger('default_value_minor')->nullable();
                $t->unsignedInteger('default_percentage_basis_points')->nullable();
                $t->char('currency_code', 3)->nullable();
                $t->string('taxable_status', 30)->default('not_configured');
                $t->string('insurance_status', 30)->default('not_configured');
                $t->boolean('affects_gross')->default(false);
                $t->boolean('affects_net')->default(false);
                $t->unsignedInteger('display_order')->default(0);
                $t->date('effective_from')->nullable();
                $t->date('effective_to')->nullable();
                $t->string('status', 20)->default('active');
                $t->boolean('is_system')->default(false);
                $t->string('system_key', 50)->nullable();
                $t->unsignedInteger('version')->default(1);
                $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->unique(['company_id', 'code']);
                $t->index(['company_id', 'status', 'category']);
            });
        }

        if (! Schema::hasTable('employee_salary_component_assignments')) {
            Schema::create('employee_salary_component_assignments', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('company_id')->constrained()->restrictOnDelete();
                $t->foreignId('employee_id')->constrained()->restrictOnDelete();
                $t->unsignedBigInteger('salary_component_id');
                $t->bigInteger('amount_minor')->nullable();
                $t->char('currency_code', 3)->nullable();
                $t->unsignedInteger('percentage_basis_points')->nullable();
                $t->bigInteger('quantity_basis_points')->nullable();
                $t->bigInteger('rate_minor')->nullable();
                $t->date('effective_from');
                $t->date('effective_to')->nullable();
                $t->string('source', 30)->default('manual');
                $t->string('status', 20)->default('active');
                $t->unsignedInteger('version')->default(1);
                $t->uuid('idempotency_key');
                $t->unsignedBigInteger('created_by');
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique(['company_id', 'idempotency_key'], 'salary_assignment_idempotency_unique');
                $t->index(['company_id', 'employee_id', 'salary_component_id', 'effective_from'], 'salary_assignment_effective_idx');
                $t->foreign('salary_component_id', 'salary_assignment_component_fk')->references('id')->on('salary_components')->restrictOnDelete();
                $t->foreign('created_by', 'salary_assignment_creator_fk')->references('id')->on('users')->restrictOnDelete();
                $t->foreign('updated_by', 'salary_assignment_updater_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('employee_salary_component_assignments') && ! in_array('salary_assignment_component_fk', array_column(Schema::getForeignKeys('employee_salary_component_assignments'), 'name'), true)) {
            Schema::table('employee_salary_component_assignments', function (Blueprint $t): void {
                $t->foreign('salary_component_id', 'salary_assignment_component_fk')->references('id')->on('salary_components')->restrictOnDelete();
                $t->foreign('created_by', 'salary_assignment_creator_fk')->references('id')->on('users')->restrictOnDelete();
                $t->foreign('updated_by', 'salary_assignment_updater_fk')->references('id')->on('users')->nullOnDelete();
                $t->unique(['company_id', 'idempotency_key'], 'salary_assignment_idempotency_unique');
                $t->index(['company_id', 'employee_id', 'salary_component_id', 'effective_from'], 'salary_assignment_effective_idx');
            });
        }

        DB::statement("ALTER TABLE payroll_runs MODIFY status ENUM('draft','calculating','calculated','validation_failed','submitted','approved','rejected','finalized','failed','cancelled','locked','paid') NOT NULL");
        Schema::table('payroll_runs', function (Blueprint $t): void {
            $t->uuid('uuid')->nullable()->unique()->after('id');
            $t->foreignId('payroll_period_id')->nullable()->after('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('payroll_input_snapshot_id')->nullable()->after('payroll_period_id')->constrained()->restrictOnDelete();
            $t->string('run_number', 80)->nullable()->after('code');
            $t->char('currency_code', 3)->default('VND')->after('status');
            $t->unsignedInteger('calculation_version')->default(0);
            $t->unsignedInteger('employee_count')->default(0);
            $t->bigInteger('gross_total_minor')->default(0);
            $t->bigInteger('deduction_total_minor')->default(0);
            $t->bigInteger('employer_contribution_total_minor')->default(0);
            $t->bigInteger('net_total_minor')->default(0);
            $t->timestamp('calculated_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('finalized_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->uuid('idempotency_key')->nullable();
            $t->unique(['company_id', 'run_number']);
            $t->unique(['company_id', 'idempotency_key']);
            $t->unique(['company_id', 'payroll_period_id'], 'payroll_active_period_unique');
        });

        Schema::table('payroll_items', function (Blueprint $t): void {
            $t->uuid('uuid')->nullable()->unique()->after('id');
            $t->foreignId('company_id')->nullable()->after('uuid')->constrained()->restrictOnDelete();
            $t->unsignedInteger('calculation_version')->default(1);
            $t->string('employee_number_snapshot')->nullable();
            $t->string('employee_name_snapshot')->nullable();
            $t->string('organization_unit_snapshot')->nullable();
            $t->string('job_position_snapshot')->nullable();
            $t->char('currency_code', 3)->default('VND');
            foreach (['scheduled_minutes', 'worked_minutes', 'paid_leave_minutes', 'unpaid_leave_minutes', 'approved_overtime_minutes'] as $column) {
                $t->unsignedInteger($column)->default(0);
            }
            $t->bigInteger('gross_amount_minor')->default(0);
            $t->bigInteger('deduction_amount_minor')->default(0);
            $t->bigInteger('employer_contribution_amount_minor')->default(0);
            $t->bigInteger('net_amount_minor')->default(0);
            $t->string('calculation_status', 30)->default('calculated');
            $t->string('validation_status', 30)->default('valid');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('calculated_at')->nullable();
        });

        Schema::create('payroll_item_components', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('salary_component_id')->nullable()->constrained()->nullOnDelete();
            $t->string('component_code_snapshot', 50);
            $t->string('component_name_snapshot');
            $t->string('category', 30);
            $t->string('calculation_type', 30);
            $t->string('source_type', 40);
            $t->unsignedBigInteger('source_id')->nullable();
            $t->bigInteger('quantity_basis_points')->nullable();
            $t->bigInteger('rate_minor')->nullable();
            $t->unsignedInteger('percentage_basis_points')->nullable();
            $t->bigInteger('base_amount_minor')->nullable();
            $t->bigInteger('amount_minor');
            $t->char('currency_code', 3);
            $t->unsignedInteger('calculation_order');
            $t->json('calculation_metadata')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['payroll_item_id', 'calculation_order']);
            $t->index(['company_id', 'payroll_run_id', 'payroll_item_id'], 'payroll_component_scope_idx');
        });

        Schema::create('payroll_validation_issues', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payroll_item_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('severity', 20);
            $t->string('code', 80);
            $t->string('safe_message');
            $t->json('metadata')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'payroll_run_id', 'severity', 'resolved_at'], 'payroll_issue_scope_idx');
        });

        Schema::create('payroll_run_histories', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->string('action', 40);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version_before');
            $t->unsignedInteger('version_after');
            $t->string('safe_reason', 2000)->nullable();
            $t->json('metadata')->nullable();
            $t->uuid('idempotency_key');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['company_id', 'payroll_run_id', 'action', 'idempotency_key'], 'payroll_history_idempotency_unique');
            $t->index(['payroll_run_id', 'created_at']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Payroll engine foundation migration is intentionally irreversible; use a reviewed forward migration.');
    }
};
