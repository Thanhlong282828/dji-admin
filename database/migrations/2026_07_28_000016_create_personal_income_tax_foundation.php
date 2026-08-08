<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->char('currency_code', 3);
            $table->string('tax_method', 30);
            $table->string('residency_group', 30)->default('resident');
            $table->string('rounding_rule', 20)->default('half_up');
            $table->string('rounding_stage', 20)->default('total');
            $table->boolean('floor_taxable_income_at_zero')->default(true);
            $table->unsignedInteger('flat_rate_basis_points')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code', 'version']);
            $table->index(['company_id', 'residency_group', 'status', 'effective_from', 'effective_to'], 'tax_policy_effective_idx');
        });

        Schema::create('tax_policy_brackets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedBigInteger('lower_bound_minor');
            $table->unsignedBigInteger('upper_bound_minor')->nullable();
            $table->unsignedInteger('rate_basis_points');
            $table->unsignedBigInteger('quick_deduction_minor')->nullable();
            $table->unsignedBigInteger('fixed_amount_minor')->nullable();
            $table->timestamps();
            $table->unique(['tax_policy_id', 'sequence']);
        });

        Schema::create('tax_policy_deductions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_policy_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('deduction_type', 40);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedInteger('rate_basis_points')->nullable();
            $table->unsignedBigInteger('maximum_amount_minor')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['tax_policy_id', 'code']);
        });

        Schema::create('employee_tax_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('tax_status', 30);
            $table->string('residency_status', 30);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->boolean('personal_deduction_eligible')->default(true);
            $table->string('withholding_method', 30)->default('policy');
            $table->unsignedBigInteger('base_override_minor')->nullable();
            $table->text('exemption_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'effective_from', 'effective_to'], 'employee_tax_profile_effective_idx');
        });

        Schema::create('employee_tax_dependents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('relationship', 50);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('deduction_eligible')->default(false);
            $table->string('approval_status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('reference_number')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'approval_status', 'effective_from', 'effective_to'], 'employee_tax_dependent_effective_idx');
        });

        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->foreignId('tax_policy_id')->nullable()->after('insurance_policy_version')->constrained()->restrictOnDelete();
            $table->unsignedInteger('tax_policy_version')->nullable()->after('tax_policy_id');
            $table->bigInteger('taxable_income_before_floor_minor')->default(0)->after('tax_policy_version');
            $table->unsignedBigInteger('taxable_income_minor')->default(0)->after('taxable_income_before_floor_minor');
            $table->unsignedBigInteger('personal_income_tax_minor')->default(0)->after('taxable_income_minor');
            $table->bigInteger('net_before_personal_income_tax_minor')->default(0)->after('personal_income_tax_minor');
            $table->bigInteger('net_after_personal_income_tax_minor')->default(0)->after('net_before_personal_income_tax_minor');
        });

        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('personal_income_tax_total_minor')->default(0)->after('insurance_employer_total_minor');
            $table->bigInteger('net_after_personal_income_tax_total_minor')->default(0)->after('net_total_minor');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropColumn(['personal_income_tax_total_minor', 'net_after_personal_income_tax_total_minor']);
        });
        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_policy_id');
            $table->dropColumn(['tax_policy_version', 'taxable_income_before_floor_minor', 'taxable_income_minor', 'personal_income_tax_minor', 'net_before_personal_income_tax_minor', 'net_after_personal_income_tax_minor']);
        });
        Schema::dropIfExists('employee_tax_dependents');
        Schema::dropIfExists('employee_tax_profiles');
        Schema::dropIfExists('tax_policy_deductions');
        Schema::dropIfExists('tax_policy_brackets');
        Schema::dropIfExists('tax_policies');
    }
};
