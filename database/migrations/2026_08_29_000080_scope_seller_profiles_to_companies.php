<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('seller_profiles', 'company_id')) {
            Schema::table('seller_profiles', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->restrictOnDelete();
                $table->index(['company_id', 'status'], 'seller_profiles_company_status');
            });
        }

        DB::table('seller_profiles')->orderBy('id')->each(function (object $profile): void {
            $companyIds = DB::table('company_user_access')->where('user_id', $profile->user_id)->orderBy('company_id')->pluck('company_id');
            if ($companyIds->count() === 1) {
                DB::table('seller_profiles')->where('id', $profile->id)->update(['company_id' => $companyIds->first()]);
            }
        });

        $indexes = collect(Schema::getIndexes('seller_profiles'))->pluck('name');
        if (! $indexes->contains('seller_profiles_user_id_index')) {
            Schema::table('seller_profiles', fn (Blueprint $table) => $table->index('user_id', 'seller_profiles_user_id_index'));
        }
        if ($indexes->contains('seller_profiles_user_id_unique')) {
            Schema::table('seller_profiles', fn (Blueprint $table) => $table->dropUnique('seller_profiles_user_id_unique'));
        }
        if (! $indexes->contains('seller_profiles_company_user_unique')) {
            Schema::table('seller_profiles', fn (Blueprint $table) => $table->unique(['company_id', 'user_id'], 'seller_profiles_company_user_unique'));
        }
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            $table->dropUnique('seller_profiles_company_user_unique');
            $table->unique('user_id');
            $table->dropIndex('seller_profiles_user_id_index');
            $table->dropIndex('seller_profiles_company_status');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
