<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('status', 20)->default('active')->index();
            $table->string('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('branch_user_access', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['branch_id', 'user_id']);
        });

        Schema::create('buyer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('seller_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_type', 80);
            $table->unsignedBigInteger('owner_id');
            $table->string('purpose', 64);
            $table->string('provider', 32);
            $table->string('provider_asset_id', 512);
            $table->string('delivery_url', 2048)->nullable();
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['owner_type', 'owner_id', 'purpose'], 'media_owner_purpose_index');
            $table->index(['provider', 'provider_asset_id'], 'media_provider_asset_index');
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('author_id')->constrained()->nullOnDelete();
            $table->string('hero_image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
            $table->string('hero_image')->nullable(false)->change();
        });
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('seller_profiles');
        Schema::dropIfExists('buyer_profiles');
        Schema::dropIfExists('branch_user_access');
        Schema::dropIfExists('branches');
    }
};
