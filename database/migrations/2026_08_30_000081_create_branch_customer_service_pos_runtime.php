<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['company_id', 'phone']);
        });

        Schema::create('company_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('VND');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('customer_visits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('commerce_customers')->restrictOnDelete();
            $table->string('status', 20)->default('checked_in')->index();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status', 'checked_in_at'], 'customer_visit_scope_status');
        });

        Schema::create('customer_visit_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_service_id')->constrained()->restrictOnDelete();
            $table->string('service_name_snapshot');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
            $table->unique(['customer_visit_id', 'company_service_id'], 'visit_service_unique');
        });

        Schema::create('customer_visit_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot', 100);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
            $table->unique(['customer_visit_id', 'product_variant_id', 'inventory_location_id'], 'visit_product_location_unique');
        });

        Schema::create('branch_pos_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('revenue_id')->nullable()->unique()->constrained('branch_management_revenues')->restrictOnDelete();
            $table->unsignedBigInteger('service_total_minor')->default(0);
            $table->unsignedBigInteger('product_total_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->default('VND');
            $table->string('payment_method', 30)->default('cash');
            $table->string('status', 20)->default('paid')->index();
            $table->timestamp('paid_at');
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'paid_at'], 'branch_pos_scope_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_pos_receipts');
        Schema::dropIfExists('customer_visit_products');
        Schema::dropIfExists('customer_visit_services');
        Schema::dropIfExists('customer_visits');
        Schema::dropIfExists('company_services');
        Schema::dropIfExists('commerce_customers');
    }
};
