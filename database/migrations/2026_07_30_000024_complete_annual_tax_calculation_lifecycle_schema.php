<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_annual_tax_reconciliations', function (Blueprint $table): void {
            $table->string('calculator_version', 50)->nullable()->after('calculation_version');
            $table->char('calculated_source_checksum', 64)->nullable()->after('source_checksum');
        });
        Schema::table('payroll_annual_tax_validation_issues', function (Blueprint $table): void {
            $table->string('validation_stage', 30)->default('source_collection')->after('severity');
            $table->char('fingerprint', 64)->nullable()->after('source_reference');
            $table->unique(['annual_tax_reconciliation_id', 'fingerprint'], 'patvi_fingerprint_uq');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Annual Tax schema is forward-only; use a corrective migration.');
    }
};
