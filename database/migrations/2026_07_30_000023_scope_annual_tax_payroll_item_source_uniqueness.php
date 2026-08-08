<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_annual_tax_sources', function (Blueprint $table): void {
            $table->dropUnique('pats_item_uq');
            $table->unique(
                ['annual_tax_reconciliation_id', 'source_type', 'payroll_item_id'],
                'pats_type_item_uq',
            );
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Annual Tax schema is forward-only; create a corrective migration instead.');
    }
};
