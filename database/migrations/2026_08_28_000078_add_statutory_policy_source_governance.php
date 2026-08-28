<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_policies', function (Blueprint $table): void {
            $table->char('jurisdiction', 2)->default('VN')->after('company_id');
            $table->string('source_reference', 1000)->nullable()->after('effective_to');
            $table->timestamp('source_verified_at')->nullable()->after('source_reference');
        });
        Schema::table('insurance_policies', function (Blueprint $table): void {
            $table->char('jurisdiction', 2)->default('VN')->after('company_id');
            $table->string('source_reference', 1000)->nullable()->after('effective_to');
            $table->timestamp('source_verified_at')->nullable()->after('source_reference');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_policies', fn (Blueprint $table) => $table->dropColumn(['jurisdiction', 'source_reference', 'source_verified_at']));
        Schema::table('tax_policies', fn (Blueprint $table) => $table->dropColumn(['jurisdiction', 'source_reference', 'source_verified_at']));
    }
};
