<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_accounting_journal_lines', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('currency')->constrained()->restrictOnDelete();
            $table->string('branch_name_snapshot')->nullable()->after('branch_id');
            $table->index(['company_id', 'branch_id', 'journal_id'], 'payroll_journal_line_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_accounting_journal_lines', function (Blueprint $table): void {
            $table->dropIndex('payroll_journal_line_branch_idx');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('branch_name_snapshot');
        });
    }
};
