<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('media_assets')->where('provider', 'local')->whereNotNull('provider_asset_id')->update([
            'delivery_url' => DB::raw("CONCAT('/storage/', TRIM(LEADING '/' FROM provider_asset_id))"),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Origin-independent local URLs remain valid after rollback. Reintroducing
        // an environment-specific APP_URL into historical rows would be unsafe.
    }
};
