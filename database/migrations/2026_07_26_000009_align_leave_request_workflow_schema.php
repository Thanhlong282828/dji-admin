<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $invalidApprovers = DB::table('leave_requests as requests')
            ->leftJoin('users', 'users.id', '=', 'requests.approved_by')
            ->whereNotNull('requests.approved_by')
            ->whereNull('users.id')
            ->count();
        if ($invalidApprovers > 0) {
            throw new RuntimeException('Cannot align leave approved_by: existing values do not reference users.');
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropForeign('leave_requests_approved_by_foreign');
        });
        DB::statement("ALTER TABLE leave_requests MODIFY status ENUM('draft','pending','submitted','approved','rejected','cancelled','withdrawn') NOT NULL");
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->foreign('approved_by', 'leave_requests_approved_by_foreign')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('leave_requests')->whereIn('status', ['submitted', 'withdrawn'])->exists()) {
            throw new RuntimeException('Cannot rollback leave workflow enum while submitted/withdrawn requests exist.');
        }
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropForeign('leave_requests_approved_by_foreign');
        });
        DB::statement("ALTER TABLE leave_requests MODIFY status ENUM('draft','pending','approved','rejected','cancelled') NOT NULL");
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->foreign('approved_by', 'leave_requests_approved_by_foreign')->references('id')->on('employees')->nullOnDelete();
        });
    }
};
