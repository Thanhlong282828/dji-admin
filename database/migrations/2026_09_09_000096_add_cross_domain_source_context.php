<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['finance_expenses', 'purchase_requests', 'finance_assets'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->string('source_type', 80)->nullable()->after('company_id');
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                $table->string('source_reference', 100)->nullable()->after('source_id');
                $table->string('source_idempotency_key', 191)->nullable()->after('source_reference');
                $table->index(['company_id', 'source_type', 'source_id'], $tableName.'_source_idx');
                $table->unique('source_idempotency_key', $tableName.'_source_idempotency_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (['finance_expenses', 'purchase_requests', 'finance_assets'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_source_idx');
                $table->dropUnique($tableName.'_source_idempotency_unique');
                $table->dropColumn(['source_type', 'source_id', 'source_reference', 'source_idempotency_key']);
            });
        }
    }
};
