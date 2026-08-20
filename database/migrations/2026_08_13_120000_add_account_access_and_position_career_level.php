<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'access_enabled')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('access_enabled')->default(true)->after('password')->index();
            });
        }
        if (! Schema::hasColumn('job_positions', 'career_level')) {
            Schema::table('job_positions', function (Blueprint $table): void {
                $table->unsignedSmallInteger('career_level')->nullable()->after('job_grade')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_positions', 'career_level')) {
            Schema::table('job_positions', fn (Blueprint $table) => $table->dropColumn('career_level'));
        }
        if (Schema::hasColumn('users', 'access_enabled')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('access_enabled'));
        }
    }
};
