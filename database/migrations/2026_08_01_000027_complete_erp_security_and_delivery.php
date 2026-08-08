<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('erp_connections', 'endpoint_reference')) {
            Schema::table('erp_connections', function (Blueprint $t): void {
                $t->string('endpoint_reference', 255)->nullable()->after('provider');
                $t->timestamp('last_verified_at')->nullable()->after('status');
                $t->foreignId('updated_by')->nullable()->after('created_by')->constrained('users', indexName: 'erp_conn_updater_fk')->nullOnDelete();
            });
        }
        Schema::table('erp_mapping_rules', function (Blueprint $t): void {
            $t->index('erp_connection_id', 'erp_map_connection_fk_idx');
        });
        Schema::table('erp_mapping_rules', function (Blueprint $t): void {
            $t->dropUnique('erp_map_conn_account_uq');
            $t->unsignedInteger('version')->default(1)->after('external_account_code');
            $t->date('effective_from')->after('version');
            $t->date('effective_to')->nullable()->after('effective_from');
            $t->foreignId('created_by')->after('status')->constrained('users', indexName: 'erp_map_creator_fk')->restrictOnDelete();
            $t->foreignId('updated_by')->nullable()->after('created_by')->constrained('users', indexName: 'erp_map_updater_fk')->nullOnDelete();
            $t->unique(['erp_connection_id', 'source_account_code', 'version'], 'erp_map_conn_account_ver_uq');
            $t->index(['company_id', 'effective_from', 'effective_to'], 'erp_map_effective_idx');
        });
        Schema::table('erp_exports', function (Blueprint $t): void {
            $t->unsignedInteger('attempt_count')->default(0)->after('export_version');
            $t->timestamp('last_attempt_at')->nullable()->after('exported_at');
        });
    }

    public function down(): void
    {
        Schema::table('erp_exports', fn (Blueprint $t) => $t->dropColumn(['attempt_count', 'last_attempt_at']));
        Schema::table('erp_mapping_rules', function (Blueprint $t): void {
            $t->dropUnique('erp_map_conn_account_ver_uq');
            $t->dropIndex('erp_map_effective_idx');
            $t->dropForeign('erp_map_creator_fk');
            $t->dropForeign('erp_map_updater_fk');
            $t->dropColumn(['version', 'effective_from', 'effective_to', 'created_by', 'updated_by']);
            $t->unique(['erp_connection_id', 'source_account_code'], 'erp_map_conn_account_uq');
        });
        Schema::table('erp_connections', function (Blueprint $t): void {
            $t->dropForeign('erp_conn_updater_fk');
            $t->dropColumn(['endpoint_reference', 'last_verified_at', 'updated_by']);
        });
    }
};
