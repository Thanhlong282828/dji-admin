<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table): void {
            $table->string('reservation_status', 20)->default('none')->after('status')->index();
            $table->timestamp('reserved_at')->nullable()->after('reservation_status');
            $table->timestamp('released_at')->nullable()->after('reserved_at');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table): void {
            $table->dropIndex(['reservation_status']);
            $table->dropColumn(['reservation_status', 'reserved_at', 'released_at']);
        });
    }
};
