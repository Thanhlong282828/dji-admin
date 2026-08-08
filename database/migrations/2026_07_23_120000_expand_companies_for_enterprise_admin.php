<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('short_name', 100)->nullable()->after('name');
            $table->unique('tax_code');
            $table->string('email')->nullable()->after('tax_code');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('website')->nullable()->after('phone');
            // TODO(Media): add FK when the disabled Media module owns a stable media table.
            $table->unsignedBigInteger('logo_media_id')->nullable()->after('website');
            $table->string('address')->nullable()->after('logo_media_id');
            $table->char('currency_code', 3)->default('VND')->after('country_code');
            $table->string('legal_representative')->nullable()->after('timezone');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('legal_representative');
            $table->text('description')->nullable()->after('status');
            $table->foreignId('created_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->index('name');
            $table->index('status');
            $table->index('created_at');
        });

        DB::table('companies')->update([
            'currency_code' => DB::raw('currency'),
            'status' => DB::raw("IF(is_active = 1, 'active', 'inactive')"),
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropUnique(['tax_code']);
            $table->dropIndex(['name']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['short_name', 'email', 'phone', 'website', 'logo_media_id', 'address', 'currency_code', 'legal_representative', 'status', 'description', 'created_by', 'updated_by', 'deleted_at']);
        });
    }
};
