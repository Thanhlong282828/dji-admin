<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('procurement_vendors')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->date('acceptance_date');
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3)->default('VND');
            $table->unsignedBigInteger('total_minor');
            $table->string('idempotency_key', 120);
            $table->foreignId('accounting_policy_id')->nullable()->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'purchase_order_id', 'status'], 'service_acceptance_po_status_idx');
        });

        Schema::create('service_acceptance_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_acceptance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('cost_center_code_snapshot', 60)->nullable();
            $table->string('cost_center_name_snapshot')->nullable();
            $table->string('procurement_classification', 30);
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('account_code_snapshot', 60);
            $table->string('account_name_snapshot');
            $table->unsignedInteger('accepted_quantity');
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('accepted_minor');
            $table->timestamps();
            $table->unique(['service_acceptance_id', 'purchase_order_line_id'], 'service_acceptance_line_unique');
            $table->index('purchase_order_line_id', 'service_acceptance_po_line_idx');
        });

        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->foreignId('service_acceptance_line_id')->nullable()->after('goods_receipt_line_id')->constrained()->restrictOnDelete();
            $table->index('service_acceptance_line_id', 'vendor_bill_service_acceptance_idx');
        });
        Schema::table('vendor_bills', function (Blueprint $table): void {
            $table->foreignId('accounting_posting_policy_id')->nullable()->after('tax_status')->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_posting_policy_version')->nullable()->after('accounting_posting_policy_id');
            $table->foreignId('accounting_journal_id')->nullable()->after('accounting_posting_policy_version')->constrained('payroll_accounting_journals')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accounting_journal_id');
            $table->dropConstrainedForeignId('accounting_posting_policy_id');
            $table->dropColumn('accounting_posting_policy_version');
        });
        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->dropIndex('vendor_bill_service_acceptance_idx');
            $table->dropConstrainedForeignId('service_acceptance_line_id');
        });
        Schema::dropIfExists('service_acceptance_lines');
        Schema::dropIfExists('service_acceptances');
    }
};
