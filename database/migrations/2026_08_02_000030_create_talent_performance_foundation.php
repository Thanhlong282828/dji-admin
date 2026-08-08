<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('performance_cycles', 'talent_status')) {
            Schema::table('performance_cycles', function (Blueprint $table): void {
                $table->date('period_start')->nullable()->after('name');
                $table->date('period_end')->nullable()->after('period_start');
                $table->string('talent_status', 32)->default('draft')->after('status');
                $table->foreignId('created_by')->nullable()->after('talent_status')->constrained('users')->nullOnDelete();
                $table->char('checksum', 64)->nullable()->after('created_by');
                $table->string('idempotency_key', 128)->nullable()->after('checksum');
                $table->unique(['company_id', 'idempotency_key'], 'performance_cycles_company_id_idempotency_key_unique');
                $table->index(['company_id', 'talent_status', 'period_start'], 'performance_cycles_company_status_period_index');
            });
        }
        if (! Schema::hasTable('employee_goals')) {
            Schema::create('employee_goals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
                $table->foreignId('cycle_id')->constrained('performance_cycles')->restrictOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('weight', 5, 2);
                $table->decimal('progress', 5, 2)->default(0);
                $table->string('status', 32)->default('assigned');
                $table->string('idempotency_key', 128);
                $table->timestamps();
                $table->unique(['company_id', 'idempotency_key']);
                $table->index(['company_id', 'employee_id', 'cycle_id']);
            });
        }
        if (! Schema::hasTable('goal_progress_history')) {
            Schema::create('goal_progress_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('goal_id')->constrained('employee_goals')->restrictOnDelete();
                $table->decimal('old_value', 5, 2);
                $table->decimal('new_value', 5, 2);
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->char('checksum', 64);
                $table->string('idempotency_key', 128);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['goal_id', 'idempotency_key']);
            });
        }
        if (! Schema::hasTable('competency_frameworks')) {
            Schema::create('competency_frameworks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('name');
                $table->unsignedInteger('version');
                $table->char('checksum', 64);
                $table->timestamps();
                $table->unique(['company_id', 'name', 'version']);
            });
        }
        if (! Schema::hasTable('competencies')) {
            Schema::create('competencies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('framework_id')->constrained('competency_frameworks')->restrictOnDelete();
                $table->string('code', 64);
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['framework_id', 'code']);
            });
        }
        if (! Schema::hasTable('employee_competencies')) {
            Schema::create('employee_competencies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
                $table->foreignId('competency_id')->constrained('competencies')->restrictOnDelete();
                $table->unsignedTinyInteger('level');
                $table->date('effective_date');
                $table->char('checksum', 64);
                $table->timestamp('created_at')->useCurrent();
                $table->index(['company_id', 'employee_id', 'effective_date'], 'employee_competency_effective_index');
            });
        }
        if (! collect(Schema::getIndexes('employee_competencies'))->contains(fn (array $index): bool => ($index['name'] ?? null) === 'employee_competency_effective_index')) {
            Schema::table('employee_competencies', fn (Blueprint $table): mixed => $table->index(['company_id', 'employee_id', 'effective_date'], 'employee_competency_effective_index'));
        }
        if (! Schema::hasTable('performance_reviews')) {
            Schema::create('performance_reviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
                $table->foreignId('cycle_id')->constrained('performance_cycles')->restrictOnDelete();
                $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
                $table->string('status', 32)->default('in_progress');
                $table->decimal('overall_rating', 4, 2)->nullable();
                $table->char('checksum', 64);
                $table->string('idempotency_key', 128);
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'employee_id', 'cycle_id']);
                $table->unique(['company_id', 'idempotency_key']);
            });
        }
        if (! Schema::hasTable('performance_review_items')) {
            Schema::create('performance_review_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('review_id')->constrained('performance_reviews')->restrictOnDelete();
                $table->foreignId('goal_id')->nullable()->constrained('employee_goals')->restrictOnDelete();
                $table->decimal('score', 4, 2);
                $table->text('comment')->nullable();
                $table->char('checksum', 64);
                $table->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('performance_review_history')) {
            Schema::create('performance_review_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('review_id')->constrained('performance_reviews')->restrictOnDelete();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->decimal('rating', 4, 2)->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->char('checksum', 64);
                $table->string('idempotency_key', 128);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['review_id', 'idempotency_key']);
            });
        }
        if (! Schema::hasTable('development_plans')) {
            Schema::create('development_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
                $table->text('goal');
                $table->string('status', 32)->default('draft');
                $table->date('target_date');
                $table->char('checksum', 64);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('talent_management_outbox')) {
            Schema::create('talent_management_outbox', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('event_type', 128);
                $table->string('aggregate_type', 64);
                $table->unsignedBigInteger('aggregate_id');
                $table->json('payload');
                $table->char('payload_checksum', 64);
                $table->timestamp('occurred_at');
                $table->timestamp('published_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['company_id', 'published_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['talent_management_outbox', 'development_plans', 'performance_review_history', 'performance_review_items', 'performance_reviews', 'employee_competencies', 'competencies', 'competency_frameworks', 'goal_progress_history', 'employee_goals'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('performance_cycles', function (Blueprint $table): void {
            $table->dropUnique('performance_cycles_company_id_idempotency_key_unique');
            $table->dropIndex('performance_cycles_company_status_period_index');
            $table->dropForeign(['created_by']);
            $table->dropColumn(['period_start', 'period_end', 'talent_status', 'created_by', 'checksum', 'idempotency_key']);
        });
    }
};
