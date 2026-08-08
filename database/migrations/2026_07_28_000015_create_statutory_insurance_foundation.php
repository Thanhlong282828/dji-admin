<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('draft');
            $table->char('currency_code', 3);
            $table->string('rounding_rule', 30)->default('half_up');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code', 'version']);
            $table->index(['company_id', 'status', 'effective_from', 'effective_to'], 'insurance_policy_effective_idx');
        });

        Schema::create('insurance_policy_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('insurance_policy_id')->constrained()->cascadeOnDelete();
            $table->string('insurance_type', 40);
            $table->unsignedInteger('employee_rate_basis_points')->default(0);
            $table->unsignedInteger('employer_rate_basis_points')->default(0);
            $table->unsignedBigInteger('minimum_base_minor')->nullable();
            $table->unsignedBigInteger('maximum_base_minor')->nullable();
            $table->unsignedInteger('calculation_order')->default(0);
            $table->timestamps();
            $table->unique(['insurance_policy_id', 'insurance_type']);
        });

        Schema::create('employee_insurance_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('participation_status', 30);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('insurance_number')->nullable();
            $table->unsignedBigInteger('contribution_base_override_minor')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->text('exemption_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'effective_from', 'effective_to'], 'insurance_profile_effective_idx');
        });

        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->foreignId('insurance_policy_id')->nullable()->after('holiday_overtime_minutes')->constrained()->restrictOnDelete();
            $table->unsignedInteger('insurance_policy_version')->nullable()->after('insurance_policy_id');
            $table->unsignedBigInteger('insurance_contribution_base_minor')->default(0)->after('insurance_policy_version');
            $table->unsignedBigInteger('insurance_employee_total_minor')->default(0)->after('insurance_contribution_base_minor');
            $table->unsignedBigInteger('insurance_employer_total_minor')->default(0)->after('insurance_employee_total_minor');
        });

        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('insurance_employee_total_minor')->default(0)->after('employer_contribution_total_minor');
            $table->unsignedBigInteger('insurance_employer_total_minor')->default(0)->after('insurance_employee_total_minor');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Statutory insurance foundation is forward-only to protect historical payroll calculations.');
    }
};
