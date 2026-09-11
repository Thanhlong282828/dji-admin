<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table): void {
            $table->string('procurement_classification', 30)->nullable()->after('description');
            $table->foreignId('account_id')->nullable()->after('procurement_classification')->constrained('accounting_accounts')->restrictOnDelete();
        });
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->string('procurement_classification', 30)->nullable()->after('cost_center_name_snapshot');
            $table->foreignId('account_id')->nullable()->after('procurement_classification')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('account_code_snapshot', 60)->nullable()->after('account_id');
            $table->string('account_name_snapshot')->nullable()->after('account_code_snapshot');
            $table->index(['purchase_order_id', 'procurement_classification'], 'po_lines_classification_index');
        });
        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->string('procurement_classification', 30)->nullable()->after('cost_center_name_snapshot');
            $table->foreignId('account_id')->nullable()->after('procurement_classification')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('account_code_snapshot', 60)->nullable()->after('account_id');
            $table->string('account_name_snapshot')->nullable()->after('account_code_snapshot');
            $table->index(['purchase_order_line_id', 'procurement_classification'], 'bill_lines_po_classification_index');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->dropIndex('bill_lines_po_classification_index');
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn(['procurement_classification', 'account_code_snapshot', 'account_name_snapshot']);
        });
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropIndex('po_lines_classification_index');
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn(['procurement_classification', 'account_code_snapshot', 'account_name_snapshot']);
        });
        Schema::table('purchase_request_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn('procurement_classification');
        });
    }
};
