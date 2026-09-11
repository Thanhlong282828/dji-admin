<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 180);
            $table->string('tax_code', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 500)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->string('status', 20)->default('active');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status', 'name']);
        });

        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 80);
            $table->date('requested_date');
            $table->date('needed_date')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('purchase_request_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 500);
            $table->unsignedInteger('requested_quantity');
            $table->unsignedBigInteger('estimated_unit_cost_minor');
            $table->unsignedBigInteger('estimated_total_minor');
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('procurement_vendors')->restrictOnDelete();
            $table->string('reference', 80);
            $table->char('currency', 3)->default('VND');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->unique('purchase_request_id');
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 500);
            $table->unsignedInteger('ordered_quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
        });

        Schema::create('procurement_goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->string('reference', 80);
            $table->date('receipt_date');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'purchase_order_id', 'status'], 'proc_gr_po_status_idx');
        });

        Schema::create('procurement_goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('procurement_goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('received_quantity');
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('line_value_minor');
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['goods_receipt_id', 'purchase_order_line_id'], 'proc_gr_line_unique');
        });

        Schema::create('vendor_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('procurement_vendors')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 100);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->char('currency', 3)->default('VND');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('tax_status', 40)->default('configuration_required');
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'vendor_id', 'invoice_number'], 'vendor_bill_invoice_unique');
            $table->index(['company_id', 'status', 'due_date']);
        });

        Schema::create('vendor_bill_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')->nullable()->constrained('procurement_goods_receipt_lines')->restrictOnDelete();
            $table->string('description', 500);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
        });

        Schema::create('ap_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('procurement_vendors')->restrictOnDelete();
            $table->date('payment_date');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('VND');
            $table->string('method', 30);
            $table->string('reference', 100);
            $table->string('status', 20)->default('approved');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
        });

        Schema::create('ap_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('ap_payments')->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('allocated_minor');
            $table->timestamps();
            $table->unique(['payment_id', 'vendor_bill_id']);
        });

        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'year', 'month']);
        });

        Schema::table('inventory_balances', function (Blueprint $table): void {
            $table->unsignedBigInteger('inventory_value_minor')->default(0)->after('reserved');
            $table->unsignedBigInteger('average_unit_cost_minor')->default(0)->after('inventory_value_minor');
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_cost_minor')->default(0)->after('quantity_delta');
            $table->bigInteger('movement_value_minor')->default(0)->after('unit_cost_minor');
            $table->unsignedBigInteger('balance_value_before_minor')->default(0)->after('on_hand_after');
            $table->unsignedBigInteger('balance_value_after_minor')->default(0)->after('balance_value_before_minor');
            $table->timestamp('occurred_at')->nullable()->after('reason');
        });

        DB::statement('ALTER TABLE payroll_accounting_journals MODIFY payroll_period_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE payroll_accounting_journals MODIFY accounting_policy_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE payroll_accounting_journals MODIFY policy_version INT UNSIGNED NULL');
        DB::statement('ALTER TABLE payroll_accounting_journal_lines MODIFY mapping_id BIGINT UNSIGNED NULL');
        Schema::table('payroll_accounting_journals', function (Blueprint $table): void {
            $table->foreignId('accounting_period_id')->nullable()->after('payroll_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->string('source_type', 80)->default('payroll_run')->after('branch_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('posting_type', 80)->default('payroll')->after('source_id');
            $table->string('generic_source_key', 191)->nullable()->after('posting_type')->unique();
        });
        Schema::create('accounting_journal_source_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_id')->constrained('payroll_accounting_journals')->cascadeOnDelete();
            $table->foreignId('journal_line_id')->nullable()->constrained('payroll_accounting_journal_lines')->cascadeOnDelete();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->string('posting_type', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->json('safe_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['journal_id', 'journal_line_id'], 'accounting_generic_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_source_evidence');
        Schema::table('payroll_accounting_journals', function (Blueprint $table): void {
            $table->dropForeign(['accounting_period_id']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['accounting_period_id', 'branch_id', 'source_type', 'source_id', 'posting_type', 'generic_source_key']);
        });
        DB::statement('ALTER TABLE payroll_accounting_journal_lines MODIFY mapping_id BIGINT UNSIGNED NOT NULL');
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->dropColumn(['unit_cost_minor', 'movement_value_minor', 'balance_value_before_minor', 'balance_value_after_minor', 'occurred_at']));
        Schema::table('inventory_balances', fn (Blueprint $table) => $table->dropColumn(['inventory_value_minor', 'average_unit_cost_minor']));
        foreach (['accounting_periods', 'ap_payment_allocations', 'ap_payments', 'vendor_bill_lines', 'vendor_bills', 'procurement_goods_receipt_lines', 'procurement_goods_receipts', 'purchase_order_lines', 'purchase_orders', 'purchase_request_lines', 'purchase_requests', 'procurement_vendors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
