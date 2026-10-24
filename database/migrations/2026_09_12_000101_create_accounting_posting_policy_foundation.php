<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_posting_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('policy_key', 100);
            $table->string('source_type', 80);
            $table->string('business_event', 100);
            $table->string('classification', 50)->nullable();
            $table->unsignedInteger('version');
            $table->char('currency', 3);
            $table->string('status', 20)->default('draft');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'policy_key', 'version'], 'accounting_posting_policy_version_unique');
            $table->index(['company_id', 'business_event', 'classification', 'currency', 'status'], 'accounting_posting_policy_lookup');
            $table->index(['effective_from', 'effective_to'], 'accounting_posting_policy_effective');
        });

        Schema::create('accounting_posting_policy_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accounting_posting_policy_id')->constrained(indexName: 'app_line_policy_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('entry_side', 10);
            $table->string('line_role', 30);
            $table->foreignId('account_id')->constrained('accounting_accounts', indexName: 'app_line_account_fk')->restrictOnDelete();
            $table->boolean('requires_cost_center')->default(false);
            $table->timestamps();
            $table->unique(['accounting_posting_policy_id', 'sequence'], 'accounting_posting_policy_line_sequence_unique');
            $table->unique(['accounting_posting_policy_id', 'line_role'], 'accounting_posting_policy_line_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_posting_policy_lines');
        Schema::dropIfExists('accounting_posting_policies');
    }
};
