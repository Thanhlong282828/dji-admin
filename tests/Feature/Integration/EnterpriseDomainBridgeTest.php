<?php

namespace Tests\Feature\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Integration\Domain\Services\EnterpriseDomainIntegrationBridge;
use Modules\Integration\Domain\Services\IntegrationHubService;
use Modules\Payroll\Domain\Services\ErpIntegrationBrowserFixtureService;
use Modules\Payroll\Domain\Services\GovernmentFilingBrowserFixtureService;
use Tests\TestCase;

final class EnterpriseDomainBridgeTest extends TestCase
{
    public function test_same_erp_export_intent_creates_exactly_one_outbound_message(): void
    {
        $fixture = app(ErpIntegrationBrowserFixtureService::class);
        $data = $fixture->create('testing');
        try {
            $connection = $this->connection($data['company_id'], $data['user_id']);
            $bridge = app(EnterpriseDomainIntegrationBridge::class);
            $first = $bridge->queueErpExport($data['company_id'], $data['export_id'], $connection, $data['user_id']);
            $second = $bridge->queueErpExport($data['company_id'], $data['export_id'], $connection, $data['user_id']);
            self::assertSame($first, $second);
            self::assertSame(1, DB::table('integration_outbound_messages')->where(['company_id' => $data['company_id'], 'source_type' => 'erp_export', 'source_id' => $data['export_id']])->count());
            self::assertStringNotContainsString('artifact_path', $this->string(DB::table('integration_outbound_messages')->where('id', $first)->value('payload')));
        } finally {
            $this->cleanIntegration($data['company_id']);
            $fixture->cleanup('testing');
        }
    }

    public function test_same_government_filing_intent_creates_exactly_one_outbound_message(): void
    {
        $fixture = app(GovernmentFilingBrowserFixtureService::class);
        $data = $fixture->create('testing');
        try {
            $actor = $this->numeric(DB::table('government_tax_filings')->where('id', $data['filing_id'])->value('submitted_by'));
            $connection = $this->connection($data['company_id'], $actor);
            $bridge = app(EnterpriseDomainIntegrationBridge::class);
            $first = $bridge->queueGovernmentFiling($data['company_id'], $data['filing_id'], $connection, $actor);
            $second = $bridge->queueGovernmentFiling($data['company_id'], $data['filing_id'], $connection, $actor);
            self::assertSame($first, $second);
            self::assertSame(1, DB::table('integration_outbound_messages')->where(['company_id' => $data['company_id'], 'source_type' => 'government_tax_filing', 'source_id' => $data['filing_id']])->count());
        } finally {
            $this->cleanIntegration($data['company_id']);
            $fixture->cleanup('testing');
        }
    }

    private function connection(int $company, int $actor): int
    {
        $id = app(IntegrationHubService::class)->createConnection($company, $actor, ['code' => 'BRIDGE-'.Str::random(6), 'name' => 'Domain Bridge', 'type' => 'file_export']);
        app(IntegrationHubService::class)->activate($company, $id, $actor);

        return $id;
    }

    private function cleanIntegration(int $company): void
    {
        foreach (['integration_outbox', 'integration_histories', 'integration_webhook_receipts', 'integration_external_references', 'integration_dead_letters', 'integration_delivery_attempts', 'integration_inbound_messages', 'integration_outbound_messages', 'integration_connectors', 'integration_connections'] as $table) {
            DB::table($table)->where('company_id', $company)->delete();
        }
    }

    private function numeric(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : self::fail('Expected numeric fixture value.');
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : self::fail('Expected string fixture value.');
    }
}
