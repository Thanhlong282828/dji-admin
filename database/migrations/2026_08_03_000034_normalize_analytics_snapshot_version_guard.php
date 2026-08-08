<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $names = array_column(Schema::getIndexes('workforce_analytics_snapshots'), 'name');
        if (in_array('analytics_snapshot_company_date_type_unique', $names, true)) {
            Schema::table('workforce_analytics_snapshots', fn (Blueprint $t) => $t->dropUnique('analytics_snapshot_company_date_type_unique'));
        }
    }

    public function down(): void {}
};
