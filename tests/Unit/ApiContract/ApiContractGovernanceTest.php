<?php

namespace Tests\Unit\ApiContract;

use App\Support\ApiContract\ApiContractFinding;
use App\Support\ApiContract\BreakingChangeClassifier;
use App\Support\ApiContract\SafeExampleScanner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ApiContractGovernanceTest extends TestCase
{
    public function test_classifier_distinguishes_additive_and_breaking_contract_changes(): void
    {
        $baseline = ['GET /api/v1/items' => ['operation_id' => 'itemList', 'security' => ['sanctum'], 'required' => [], 'responses' => ['200']]];
        $current = [
            'GET /api/v1/items' => ['operation_id' => 'itemList', 'security' => ['sanctum'], 'required' => ['query:company_id'], 'responses' => ['200']],
            'POST /api/v1/items' => ['operation_id' => 'itemStore', 'security' => ['sanctum'], 'required' => [], 'responses' => ['201']],
        ];

        $findings = (new BreakingChangeClassifier)->compare($baseline, $current);
        self::assertSame(['BREAKING', 'ADDITIVE'], array_column($findings, 'classification'));
    }

    public function test_safe_example_scanner_only_reports_credential_like_values(): void
    {
        $scanner = new SafeExampleScanner;
        self::assertSame([], $scanner->violations(['example' => 'admin@local.test', 'field' => 'token']));
        self::assertSame(['$.example'], $scanner->violations(['example' => 'Bearer abcdefghijklmnopqrstuvwxyz123456']));
    }

    public function test_finding_is_typed_and_rejects_unknown_severity(): void
    {
        $finding = new ApiContractFinding('API-001', 'warning', 'COVERAGE', 'Missing operation.', 'GET /api/v1/items');
        self::assertSame('API-001', $finding->toArray()['key']);
        $this->expectException(InvalidArgumentException::class);
        new ApiContractFinding('API-002', 'urgent', 'SECURITY', 'Invalid.', 'evidence');
    }
}
