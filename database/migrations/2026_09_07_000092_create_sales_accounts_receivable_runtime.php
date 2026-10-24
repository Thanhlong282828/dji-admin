<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->restrictOnDelete();
            $t->string('reference')->unique();
            $t->date('quotation_date');
            $t->date('valid_until');
            $t->char('currency', 3)->default('VND');
            $t->unsignedBigInteger('subtotal_minor');
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('tax_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->string('tax_status')->default('configuration_required');
            $t->string('status')->default('draft')->index();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('sales_quotation_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('quotation_id')->constrained('sales_quotations')->cascadeOnDelete();
            $t->foreignId('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_price_minor');
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('tax_minor')->default(0);
            $t->unsignedBigInteger('line_total_minor');
            $t->timestamps();
        });
        Schema::create('sales_orders', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->restrictOnDelete();
            $t->foreignId('quotation_id')->nullable()->unique()->constrained('sales_quotations')->restrictOnDelete();
            $t->string('reference')->unique();
            $t->date('order_date');
            $t->char('currency', 3)->default('VND');
            $t->unsignedBigInteger('subtotal_minor');
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('tax_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->string('status')->default('draft')->index();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sales_order_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $t->string('sku_snapshot')->nullable();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('fulfilled_quantity')->default(0);
            $t->unsignedBigInteger('unit_price_minor');
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('tax_minor')->default(0);
            $t->unsignedBigInteger('net_minor');
            $t->unsignedBigInteger('cogs_minor')->default(0);
            $t->timestamps();
        });
        Schema::create('sales_fulfillments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('sales_order_line_id')->constrained()->restrictOnDelete();
            $t->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $t->foreignId('inventory_movement_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('reference')->unique();
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_cogs_minor');
            $t->unsignedBigInteger('total_cogs_minor');
            $t->date('fulfilled_on');
            $t->string('status')->default('fulfilled');
            $t->foreignId('fulfilled_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['sales_order_line_id', 'reference']);
        });
        Schema::create('customer_invoices', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->restrictOnDelete();
            $t->foreignId('sales_order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->string('invoice_number')->unique();
            $t->date('invoice_date');
            $t->date('due_date');
            $t->char('currency', 3)->default('VND');
            $t->unsignedBigInteger('subtotal_minor');
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('tax_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->unsignedBigInteger('paid_minor')->default(0);
            $t->string('tax_status')->default('configuration_required');
            $t->string('status')->default('draft')->index();
            $t->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('issued_at')->nullable();
            $t->timestamps();
        });
        Schema::create('customer_invoice_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('customer_invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sales_order_line_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_price_minor');
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('tax_minor')->default(0);
            $t->unsignedBigInteger('line_total_minor');
            $t->unsignedBigInteger('cogs_minor')->default(0);
            $t->timestamps();
        });
        Schema::create('customer_collections', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->restrictOnDelete();
            $t->string('reference')->unique();
            $t->date('collection_date');
            $t->unsignedBigInteger('amount_minor');
            $t->unsignedBigInteger('allocated_minor')->default(0);
            $t->char('currency', 3)->default('VND');
            $t->string('method');
            $t->string('status')->default('recorded');
            $t->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $t->foreignId('treasury_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('customer_collection_allocations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('customer_collection_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_invoice_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('allocated_minor');
            $t->timestamps();
            $t->unique(['customer_collection_id', 'customer_invoice_id'], 'collection_invoice_unique');
        });
    }

    public function down(): void
    {
        foreach (['customer_collection_allocations', 'customer_collections', 'customer_invoice_lines', 'customer_invoices', 'sales_fulfillments', 'sales_order_lines', 'sales_orders', 'sales_quotation_lines', 'sales_quotations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
