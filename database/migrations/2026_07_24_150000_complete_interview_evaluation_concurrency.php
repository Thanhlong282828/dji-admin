<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_evaluations', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('submitted_at');
            $table->foreignId('reopened_by')->nullable()->after('version')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            $table->text('reopen_reason')->nullable()->after('reopened_at');
        });

        Schema::create('application_interview_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_interview_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['application_interview_id', 'created_at'], 'interview_history_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_interview_histories');
        Schema::table('interview_evaluations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['version', 'reopened_at', 'reopen_reason']);
        });
    }
};
