<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table): void {
            $table->string('cost_center_code_snapshot')->nullable()->after('cost_center_id');
            $table->string('cost_center_name_snapshot')->nullable()->after('cost_center_code_snapshot');
        });
        Schema::table('finance_depreciation_entries', function (Blueprint $table): void {
            $table->foreignId('cost_center_id')->nullable()->after('asset_id')->constrained()->restrictOnDelete();
            $table->string('cost_center_code_snapshot')->nullable()->after('cost_center_id');
            $table->string('cost_center_name_snapshot')->nullable()->after('cost_center_code_snapshot');
            $table->index(['company_id', 'cost_center_id', 'period_month'], 'finance_depreciation_cost_center_idx');
        });
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->string('cost_center_code_snapshot')->nullable()->after('cost_center_id');
            $table->string('cost_center_name_snapshot')->nullable()->after('cost_center_code_snapshot');
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('cost_center_id')->nullable()->after('purchase_request_id')->constrained()->restrictOnDelete();
            $table->string('cost_center_code_snapshot')->nullable()->after('cost_center_id');
            $table->string('cost_center_name_snapshot')->nullable()->after('cost_center_code_snapshot');
            $table->index(['company_id', 'cost_center_id', 'status', 'order_date'], 'purchase_order_cost_center_idx');
        });
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->foreignId('cost_center_id')->nullable()->after('purchase_request_line_id')->constrained()->restrictOnDelete();
            $table->string('cost_center_code_snapshot')->nullable()->after('cost_center_id');
            $table->string('cost_center_name_snapshot')->nullable()->after('cost_center_code_snapshot');
            $table->index(['purchase_order_id', 'cost_center_id'], 'purchase_order_line_cost_center_idx');
        });
        Schema::table('vendor_bills', function (Blueprint $table): void {
            $table->foreignId('cost_center_id')->nullable()->after('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('cost_center_code_snapshot')->nullable()->after('cost_center_id');
            $table->string('cost_center_name_snapshot')->nullable()->after('cost_center_code_snapshot');
        });
        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->foreignId('cost_center_id')->nullable()->after('goods_receipt_line_id')->constrained()->restrictOnDelete();
            $table->string('cost_center_code_snapshot')->nullable()->after('cost_center_id');
            $table->string('cost_center_name_snapshot')->nullable()->after('cost_center_code_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn(['cost_center_code_snapshot', 'cost_center_name_snapshot']);
        });
        Schema::table('vendor_bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn(['cost_center_code_snapshot', 'cost_center_name_snapshot']);
        });
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropIndex('purchase_order_line_cost_center_idx');
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn(['cost_center_code_snapshot', 'cost_center_name_snapshot']);
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purchase_order_cost_center_idx');
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn(['cost_center_code_snapshot', 'cost_center_name_snapshot']);
        });
        Schema::table('purchase_requests', fn (Blueprint $table) => $table->dropColumn(['cost_center_code_snapshot', 'cost_center_name_snapshot']));
        Schema::table('finance_depreciation_entries', function (Blueprint $table): void {
            $table->dropIndex('finance_depreciation_cost_center_idx');
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn(['cost_center_code_snapshot', 'cost_center_name_snapshot']);
        });
        Schema::table('finance_expenses', fn (Blueprint $table) => $table->dropColumn(['cost_center_code_snapshot', 'cost_center_name_snapshot']));
    }
};
