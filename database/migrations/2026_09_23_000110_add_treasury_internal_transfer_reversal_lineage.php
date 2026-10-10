<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_internal_transfers', function (Blueprint $table): void {
            $table->foreignId('reversal_of_transfer_id')->nullable()->after('id')->constrained('treasury_internal_transfers', 'id', 'treasury_transfer_reversal_of_fk')->restrictOnDelete();
            $table->unique('reversal_of_transfer_id', 'treasury_transfer_single_reversal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_internal_transfers', function (Blueprint $table): void {
            $table->dropUnique('treasury_transfer_single_reversal_unique');
            $table->dropForeign('treasury_transfer_reversal_of_fk');
            $table->dropColumn('reversal_of_transfer_id');
        });
    }
};
