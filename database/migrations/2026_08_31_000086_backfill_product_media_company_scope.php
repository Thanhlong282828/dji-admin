<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('media_assets as media')
            ->join('products as product', function ($join): void {
                $join->on('product.id', '=', 'media.owner_id')->where('media.owner_type', 'product');
            })
            ->join('stores as store', 'store.id', '=', 'product.store_id')
            ->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->whereNull('media.company_id')
            ->update(['media.company_id' => DB::raw('seller.company_id')]);
    }

    public function down(): void
    {
        // Derived tenant metadata must not be erased during rollback.
    }
};
