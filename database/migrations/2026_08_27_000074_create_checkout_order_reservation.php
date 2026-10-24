<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->string('recipient_name');
            $table->string('phone', 30);
            $table->string('province', 120);
            $table->string('district', 120);
            $table->string('ward', 120);
            $table->string('address_line', 500);
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });
        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('checkout_reference')->unique();
            $table->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('status', 24)->default('processing')->index();
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->char('currency', 3)->default('VND');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['buyer_user_id', 'idempotency_key'], 'checkout_buyer_idempotency_unique');
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_session_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 40)->unique();
            $table->uuid('checkout_reference')->index();
            $table->foreignId('buyer_user_id')->constrained('users');
            $table->foreignId('store_id')->constrained();
            $table->foreignId('seller_profile_id')->constrained();
            $table->string('store_name_snapshot');
            $table->json('shipping_address_snapshot');
            $table->string('shipping_method', 24);
            $table->string('status', 24)->default('pending_payment')->index();
            $table->string('payment_status', 20)->default('unpaid');
            $table->char('currency', 3)->default('VND');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('shipping_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->timestamp('placed_at');
            $table->timestamps();
            $table->index(['buyer_user_id', 'status', 'placed_at']);
            $table->index(['store_id', 'status', 'placed_at']);
            $table->unique(['checkout_session_id', 'store_id']);
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku_snapshot', 100);
            $table->string('product_name_snapshot');
            $table->string('variant_label_snapshot');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency', 3)->default('VND');
            $table->timestamps();
        });
        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances');
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('reserved')->index();
            $table->uuid('reference')->unique();
            $table->timestamp('reserved_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index(['product_variant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('checkout_sessions');
        Schema::dropIfExists('buyer_addresses');
    }
};
