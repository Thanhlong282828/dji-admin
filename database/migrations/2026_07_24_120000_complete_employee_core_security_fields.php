<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_credentials', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_credentials', 'credential_number')) {
                $table->text('credential_number')->nullable()->after('name');
            }
            if (! Schema::hasColumn('employee_credentials', 'document_storage_key')) {
                $table->string('document_storage_key')->nullable()->after('document_url');
            }
            if (! Schema::hasColumn('employee_credentials', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->index();
            }
        });

        Schema::table('employment_contracts', function (Blueprint $table): void {
            if (! Schema::hasColumn('employment_contracts', 'signed_storage_key')) {
                $table->string('signed_storage_key')->nullable()->after('signed_document_url');
            }
            if (! Schema::hasColumn('employment_contracts', 'termination_reason')) {
                $table->text('termination_reason')->nullable();
            }
            if (! Schema::hasColumn('employment_contracts', 'terminated_at')) {
                $table->timestamp('terminated_at')->nullable();
            }
        });

        Schema::table('data_consents', function (Blueprint $table): void {
            if (! Schema::hasColumn('data_consents', 'actor_id')) {
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('data_consents', 'reason')) {
                $table->text('reason')->nullable();
            }
            if (! Schema::hasColumn('data_consents', 'evidence_storage_key')) {
                $table->string('evidence_storage_key')->nullable();
            }
        });

        Schema::table('employee_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_documents', 'mime_type')) {
                $table->string('mime_type', 150)->nullable();
            }
            if (! Schema::hasColumn('employee_documents', 'size_bytes')) {
                $table->unsignedBigInteger('size_bytes')->default(0);
            }
            if (! Schema::hasColumn('employee_documents', 'scan_status')) {
                $table->string('scan_status', 20)->default('pending')->index();
            }
            if (! Schema::hasColumn('employee_documents', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->index();
            }
        });

        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'avatar_storage_key')) {
                $table->string('avatar_storage_key')->nullable()->after('avatar_url');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE employment_contracts MODIFY status ENUM('draft','approval','approved','active','expired','terminated','cancelled') NOT NULL");
            DB::statement("ALTER TABLE employee_credentials MODIFY type ENUM('identity','passport','work_permit','certificate','license','degree','professional_qualification','other','education','language','skill','award') NOT NULL");
        }
    }

    public function down(): void
    {
        // Security/audit data is deliberately retained; this migration is forward-only.
    }
};
