<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('store_id')->constrained()->restrictOnDelete();
            $table->index(['store_id', 'branch_id', 'status'], 'inventory_location_branch_scope');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->timestamp('fulfilled_at')->nullable()->after('paid_at');
        });
        Schema::create('commerce_payment_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('idempotency_key', 100);
            $table->string('method', 30);
            $table->string('provider', 40)->default('manual_evidence');
            $table->string('status', 20)->default('succeeded');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('VND');
            $table->string('settlement_reference', 120);
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['order_id', 'idempotency_key'], 'commerce_payment_order_idempotency');
            $table->unique(['provider', 'settlement_reference'], 'commerce_payment_provider_settlement');
            $table->index(['company_id', 'status', 'paid_at'], 'commerce_payment_company_period');
        });
        Schema::create('commerce_fulfillments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('status', 20)->default('pending');
            $table->string('carrier', 80)->nullable();
            $table->string('tracking_reference', 120)->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status'], 'commerce_fulfillment_branch_status');
        });
        Schema::table('branch_management_revenues', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->after('branch_id')->constrained()->restrictOnDelete();
            $table->unique('order_id', 'branch_revenue_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('branch_management_revenues', function (Blueprint $table): void {
            $table->dropUnique('branch_revenue_order_unique');
            $table->dropConstrainedForeignId('order_id');
        });
        Schema::dropIfExists('commerce_fulfillments');
        Schema::dropIfExists('commerce_payment_records');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['paid_at', 'fulfilled_at']);
        });
        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->dropIndex('inventory_location_branch_scope');
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
