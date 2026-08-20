<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employee_histories MODIFY event_type ENUM('transfer','promotion','demotion','salary_change','discipline','award','status_change') NOT NULL");
    }

    public function down(): void
    {
        // Forward-only widening: removing an enum member could destroy valid
        // demotion history, so production rollback must use a forward fix.
    }
};
