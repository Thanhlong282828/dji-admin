<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workforce_analytics_snapshots', function (Blueprint $t): void {
            $t->unsignedInteger('version')->default(1)->after('snapshot_type');
            $t->unique(['company_id', 'snapshot_date', 'snapshot_type', 'version'], 'analytics_snapshot_company_date_type_version_unique');
        });
    }

    public function down(): void
    {
        Schema::table('workforce_analytics_snapshots', function (Blueprint $t): void {
            $t->dropUnique('analytics_snapshot_company_date_type_version_unique');
            $t->dropColumn('version');
            $t->unique(['company_id', 'snapshot_date', 'snapshot_type'], 'analytics_snapshot_company_date_type_unique');
        });
    }
};
