<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_categories')) {
            Schema::create('tax_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->string('code', 50);
                $table->string('name', 180);
                $table->enum('direction', ['output', 'input']);
                $table->char('country_code', 2);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'direction', 'status']);
            });
        }

        Schema::create('vat_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_category_id')->constrained('tax_categories')->restrictOnDelete();
            $table->string('policy_key', 100);
            $table->unsignedInteger('version');
            $table->char('country_code', 2);
            $table->char('currency', 3);
            $table->unsignedInteger('rate_basis_points');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('tax_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('accounting_posting_policy_id')->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->enum('status', ['draft', 'submitted', 'approved', 'retired'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'policy_key', 'version']);
            $table->index(['company_id', 'tax_category_id', 'currency', 'status', 'effective_from'], 'tax_policy_resolution_idx');
            $table->index(['company_id', 'effective_from', 'effective_to'], 'tax_policy_tenant_period_idx');
        });

        Schema::create('tax_transaction_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('tax_category_id')->constrained('tax_categories')->restrictOnDelete();
            $table->foreignId('tax_policy_id')->constrained('vat_policies')->restrictOnDelete();
            $table->unsignedInteger('policy_version');
            $table->unsignedInteger('rate_basis_points');
            $table->unsignedBigInteger('taxable_amount_minor');
            $table->unsignedBigInteger('tax_amount_minor');
            $table->char('currency', 3);
            $table->date('posting_date');
            $table->foreignId('accounting_journal_id')->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->json('mapping_snapshot');
            $table->timestamps();
            $table->unique(['company_id', 'source_type', 'source_id', 'tax_category_id'], 'tax_evidence_source_unique');
            $table->index(['company_id', 'posting_date', 'currency'], 'tax_evidence_report_idx');
            $table->index(['company_id', 'tax_policy_id', 'policy_version'], 'tax_evidence_policy_idx');
        });

        foreach (['sales_quotations', 'sales_orders', 'customer_invoices', 'vendor_bills'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->restrictOnDelete();
                $table->foreignId('tax_policy_id')->nullable()->constrained('vat_policies')->restrictOnDelete();
                $table->unsignedInteger('tax_policy_version')->nullable();
                $table->unsignedInteger('tax_rate_basis_points')->nullable();
                $table->index(
                    ['tax_policy_id', 'tax_policy_version'],
                    $tableName.'_tax_policy_version_idx',
                );
            });
        }
    }

    public function down(): void
    {
        foreach (['vendor_bills', 'customer_invoices', 'sales_orders', 'sales_quotations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex($table->getTable().'_tax_policy_version_idx');
                $table->dropConstrainedForeignId('tax_category_id');
                $table->dropConstrainedForeignId('tax_policy_id');
                $table->dropColumn(['tax_policy_version', 'tax_rate_basis_points']);
            });
        }
        Schema::dropIfExists('tax_transaction_evidence');
        Schema::dropIfExists('vat_policies');
        Schema::dropIfExists('tax_categories');
    }
};
