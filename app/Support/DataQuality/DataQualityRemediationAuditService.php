<?php

namespace App\Support\DataQuality;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final readonly class DataQualityRemediationAuditService
{
    /** @var list<string> */
    private const POLICY_SOURCE_TYPES = ['customer_invoice', 'vendor_bill', 'ap_payment', 'finance_asset', 'customer_credit_note', 'customer_refund', 'inventory_adjustment', 'asset_impairment', 'asset_disposal', 'treasury_opening_balance', 'treasury_internal_transfer', 'goods_receipt', 'customer_collection', 'sales_fulfillment', 'finance_expense'];

    /** @var list<string> */
    private const SUPPORTED_RULES = ['identity.invalid_verified_phone', 'journal_without_policy', 'missing_account_mapping', 'posting_without_policy', 'posting_without_mapping', 'legacy_account_mapping_usage', 'journal_without_policy_snapshot', 'receipt_without_policy', 'collection_without_policy', 'cogs_without_inventory_source', 'expense_without_policy', 'period_without_fiscal_year'];

    public function __construct(
        private DataQualityDatabaseScope $databaseScope,
        private StagingRestoreAuditAuthorizer $stagingAuthorizer,
    ) {}

    /** @return array<string,mixed> */
    public function snapshot(?int $companyId = null, ?string $expectedDatabase = null): array
    {
        $scope = $this->databaseScope->metadata();
        $stagingAuthorization = $this->assertSafeScope($scope, $expectedDatabase);
        if ($companyId !== null && $companyId < 1) {
            throw new RuntimeException('Company scope must be a positive integer.');
        }

        $policyJournals = $this->policylessJournals($companyId);
        $missingMappings = $this->missingMappings($companyId);
        $details = [
            'identity.invalid_verified_phone' => $this->invalidVerifiedPhones($companyId),
            'journal_without_policy' => $policyJournals,
            'missing_account_mapping' => $missingMappings,
            'posting_without_policy' => $policyJournals,
            'posting_without_mapping' => $missingMappings,
            'legacy_account_mapping_usage' => $this->legacyMappings($companyId),
            'journal_without_policy_snapshot' => $this->journalsWithoutPolicySnapshot($companyId),
            'receipt_without_policy' => $this->sourcesWithoutPolicy('procurement_goods_receipts', ['received'], 'goods_receipt', $companyId),
            'collection_without_policy' => $this->sourcesWithoutPolicy('customer_collections', ['recorded'], 'customer_collection', $companyId),
            'cogs_without_inventory_source' => $this->invalidCogs($companyId),
            'expense_without_policy' => $this->sourcesWithoutPolicy('finance_expenses', ['approved', 'paid'], 'finance_expense', $companyId, 'expense_incurred'),
            'period_without_fiscal_year' => $this->periodsWithoutFiscalYear($companyId),
        ];

        foreach ($details as &$records) {
            usort($records, static fn (mixed $left, mixed $right): int => strcmp(self::recordKey($left), self::recordKey($right)));
        }
        unset($records);

        $rowSets = [];
        foreach ($details as $rule => $records) {
            $rowSets[$rule] = array_map(static fn (mixed $record): string => self::recordKey($record), $records);
        }
        $relationships = [
            $this->relationship('journal_without_policy', 'posting_without_policy', $rowSets),
            $this->relationship('missing_account_mapping', 'posting_without_mapping', $rowSets),
            $this->relationship('legacy_account_mapping_usage', 'missing_account_mapping', $rowSets),
            $this->relationship('journal_without_policy_snapshot', 'journal_without_policy', $rowSets),
        ];

        $payload = [
            'schema_version' => 1,
            'mode' => 'DRY_RUN_READ_ONLY',
            'mutation_count' => 0,
            'scope' => $scope,
            'company_id' => $companyId,
            'rules' => array_map(static fn (string $rule): array => [
                'rule_id' => $rule,
                'count' => count($details[$rule]),
                'records' => $details[$rule],
            ], self::SUPPORTED_RULES),
            'relationships' => $relationships,
        ];
        if ($stagingAuthorization !== null) {
            $payload['staging_authorization'] = $stagingAuthorization;
        }
        $payload['checksum'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $payload;
    }

    /** @param array<string,mixed> $scope */
    /**
     * @param  array<string,mixed>  $scope
     * @return array<string,mixed>|null
     */
    private function assertSafeScope(array $scope, ?string $expectedDatabase): ?array
    {
        if ($expectedDatabase === null || $expectedDatabase === '') {
            throw new RuntimeException('The exact expected database is required.');
        }
        if (self::stringValue($scope['environment'] ?? null) === 'testing') {
            if (self::stringValue($scope['database_purpose'] ?? null) !== 'TESTING' || self::boolValue($scope['database_matches'] ?? null) !== true
                || ! hash_equals($expectedDatabase, self::stringValue($scope['configured_database'] ?? null))
                || ! hash_equals($expectedDatabase, self::stringValue($scope['actual_database'] ?? null))) {
                throw new RuntimeException('Expected, configured and actual TESTING database identities must match.');
            }

            return null;
        }

        return $this->stagingAuthorizer->authorize($scope, $expectedDatabase);
    }

    /** @return list<array<string,mixed>> */
    private function invalidVerifiedPhones(?int $companyId): array
    {
        $query = DB::table('users as user')->whereNotNull('user.phone_verified_at')->where(static fn (Builder $builder): Builder => $builder->whereNull('user.phone_e164')->orWhereRaw("user.phone_e164 NOT REGEXP '^\\+84[1-9][0-9]{8,10}$'"));
        if ($companyId !== null) {
            $query->whereExists(static fn (Builder $access): Builder => $access->selectRaw('1')->from('company_user_access as access')->whereColumn('access.user_id', 'user.id')->where('access.company_id', $companyId));
        }

        return array_values($query->orderBy('user.id')->get(['user.id'])->map(function (object $row): array {
            $id = self::intProperty($row, 'id');
            $companies = Schema::hasTable('company_user_access') ? self::integerList(DB::table('company_user_access')->where('user_id', $id)->orderBy('company_id')->pluck('company_id')->all()) : [];

            return ['record_key' => 'user:'.$id, 'entity_type' => 'user', 'record_id' => $id, 'company_ids' => $companies, 'reason' => 'VERIFIED_PHONE_MISSING_OR_INVALID_E164', 'sensitive_value_redacted' => true];
        })->all());
    }

    /** @return list<array<string,mixed>> */
    private function policylessJournals(?int $companyId): array
    {
        $query = DB::table('payroll_accounting_journals as journal')->whereIn('journal.source_type', self::POLICY_SOURCE_TYPES)->whereIn('journal.status', ['internally_posted', 'exported'])
            ->whereNotExists(static fn (Builder $line): Builder => $line->selectRaw('1')->from('payroll_accounting_journal_lines as line')->whereColumn('line.journal_id', 'journal.id')->whereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_id') IS NOT NULL")->whereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_version') IS NOT NULL"));
        $this->scopeCompany($query, 'journal.company_id', $companyId);

        return array_values($query->orderBy('journal.id')->get(['journal.id', 'journal.company_id', 'journal.branch_id', 'journal.source_type', 'journal.source_id'])->map(static fn (object $row): array => ['record_key' => 'journal:'.self::intProperty($row, 'id'), 'entity_type' => 'journal', 'record_id' => self::intProperty($row, 'id'), 'company_id' => self::intProperty($row, 'company_id'), 'branch_id' => self::nullableIntProperty($row, 'branch_id'), 'source_type' => self::stringProperty($row, 'source_type'), 'source_id' => self::nullableIntProperty($row, 'source_id'), 'reason' => 'NO_LINE_WITH_POLICY_ID_AND_VERSION'])->all());
    }

    /** @return list<array<string,mixed>> */
    private function missingMappings(?int $companyId): array
    {
        $query = DB::table('payroll_accounting_journal_lines as line')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')->leftJoin('accounting_accounts as account', 'account.id', '=', 'line.account_id')
            ->whereIn('journal.source_type', self::POLICY_SOURCE_TYPES)->whereIn('journal.status', ['internally_posted', 'exported'])
            ->where(static fn (Builder $builder): Builder => $builder->whereNull('account.id')->orWhereColumn('account.company_id', '<>', 'journal.company_id')->orWhereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_id') IS NULL")->orWhereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_version') IS NULL"));
        $this->scopeCompany($query, 'journal.company_id', $companyId);

        return array_values($query->orderBy('line.id')->get(['line.id', 'line.journal_id', 'journal.company_id', 'journal.branch_id', 'line.account_id', 'account.id as existing_account_id', 'account.company_id as account_company_id', 'line.mapping_snapshot'])->map(static function (object $row): array {
            $snapshotJson = self::nullableStringProperty($row, 'mapping_snapshot');
            $snapshot = $snapshotJson === null ? [] : json_decode($snapshotJson, true);
            $reasons = [];
            if (self::nullableIntProperty($row, 'existing_account_id') === null) {
                $reasons[] = 'ACCOUNT_MISSING';
            }
            $accountCompanyId = self::nullableIntProperty($row, 'account_company_id');
            $companyId = self::intProperty($row, 'company_id');
            if ($accountCompanyId !== null && $accountCompanyId !== $companyId) {
                $reasons[] = 'ACCOUNT_CROSS_COMPANY';
            }
            if (! is_array($snapshot) || ! isset($snapshot['policy_id'])) {
                $reasons[] = 'POLICY_ID_MISSING';
            }
            if (! is_array($snapshot) || ! isset($snapshot['policy_version'])) {
                $reasons[] = 'POLICY_VERSION_MISSING';
            }

            $id = self::intProperty($row, 'id');

            return ['record_key' => 'line:'.$id, 'entity_type' => 'journal_line', 'record_id' => $id, 'journal_id' => self::intProperty($row, 'journal_id'), 'company_id' => $companyId, 'branch_id' => self::nullableIntProperty($row, 'branch_id'), 'account_id' => self::intProperty($row, 'account_id'), 'reasons' => $reasons];
        })->all());
    }

    /** @return list<array<string,mixed>> */
    private function legacyMappings(?int $companyId): array
    {
        $query = DB::table('payroll_accounting_journal_lines as line')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')->whereIn('journal.source_type', self::POLICY_SOURCE_TYPES)->whereIn('journal.status', ['internally_posted', 'exported'])->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(line.mapping_snapshot, '$.policy')) = 'canonical_source_posting_v1'");
        $this->scopeCompany($query, 'journal.company_id', $companyId);

        return array_values($query->orderBy('line.id')->get(['line.id', 'line.journal_id', 'journal.company_id', 'journal.branch_id'])->map(static fn (object $row): array => ['record_key' => 'line:'.self::intProperty($row, 'id'), 'entity_type' => 'journal_line', 'record_id' => self::intProperty($row, 'id'), 'journal_id' => self::intProperty($row, 'journal_id'), 'company_id' => self::intProperty($row, 'company_id'), 'branch_id' => self::nullableIntProperty($row, 'branch_id'), 'reason' => 'LEGACY_CANONICAL_SOURCE_POSTING_V1'])->all());
    }

    /** @return list<array<string,mixed>> */
    private function journalsWithoutPolicySnapshot(?int $companyId): array
    {
        $query = DB::table('payroll_accounting_journals as journal')->whereIn('journal.source_type', self::POLICY_SOURCE_TYPES)->whereIn('journal.status', ['internally_posted', 'exported'])->whereNotExists(static fn (Builder $line): Builder => $line->selectRaw('1')->from('payroll_accounting_journal_lines as line')->whereColumn('line.journal_id', 'journal.id')->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(line.mapping_snapshot, '$.policy')) = 'accounting_posting_policy'"));
        $this->scopeCompany($query, 'journal.company_id', $companyId);

        return array_values($query->orderBy('journal.id')->get(['journal.id', 'journal.company_id', 'journal.branch_id', 'journal.source_type', 'journal.source_id'])->map(static fn (object $row): array => ['record_key' => 'journal:'.self::intProperty($row, 'id'), 'entity_type' => 'journal', 'record_id' => self::intProperty($row, 'id'), 'company_id' => self::intProperty($row, 'company_id'), 'branch_id' => self::nullableIntProperty($row, 'branch_id'), 'source_type' => self::stringProperty($row, 'source_type'), 'source_id' => self::nullableIntProperty($row, 'source_id'), 'reason' => 'ACCOUNTING_POSTING_POLICY_SNAPSHOT_MISSING'])->all());
    }

    /**
     * @param  list<string>  $statuses
     * @return list<array<string,mixed>>
     */
    private function sourcesWithoutPolicy(string $table, array $statuses, string $sourceType, ?int $companyId, ?string $postingType = null): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }
        $query = DB::table($table.' as source')->whereIn('source.status', $statuses);
        $this->scopeCompany($query, 'source.company_id', $companyId);
        $rows = $query->orderBy('source.id')->get(['source.id', 'source.company_id', 'source.branch_id']);

        $result = [];
        foreach ($rows as $row) {
            $recordId = self::intProperty($row, 'id');
            $companyId = self::intProperty($row, 'company_id');
            $journal = DB::table('payroll_accounting_journals')->where('company_id', $companyId)->where('source_id', $recordId)->where('source_type', $sourceType)->whereIn('status', ['internally_posted', 'exported']);
            if ($postingType !== null) {
                $journal->where('posting_type', $postingType);
            }
            $journalIds = $journal->orderBy('id')->pluck('id');
            $hasPolicy = $journalIds->isNotEmpty() && DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journalIds)->whereRaw("JSON_EXTRACT(mapping_snapshot, '$.policy_id') IS NOT NULL")->exists();
            if ($hasPolicy) {
                continue;
            }

            $result[] = ['record_key' => $sourceType.':'.$recordId, 'entity_type' => $sourceType, 'record_id' => $recordId, 'company_id' => $companyId, 'branch_id' => self::nullableIntProperty($row, 'branch_id'), 'journal_ids' => self::integerList($journalIds->all()), 'reason' => $journalIds->isEmpty() ? 'JOURNAL_MISSING' : 'JOURNAL_POLICY_EVIDENCE_MISSING'];
        }

        return $result;
    }

    /** @return list<array<string,mixed>> */
    private function invalidCogs(?int $companyId): array
    {
        if (! Schema::hasTable('sales_fulfillments')) {
            return [];
        }
        $query = DB::table('sales_fulfillments as fulfillment')->leftJoin('inventory_movements as movement', 'movement.id', '=', 'fulfillment.inventory_movement_id')->where('fulfillment.status', 'fulfilled');
        $this->scopeCompany($query, 'fulfillment.company_id', $companyId);

        $result = [];
        foreach ($query->orderBy('fulfillment.id')->get(['fulfillment.id', 'fulfillment.company_id', 'fulfillment.inventory_movement_id', 'movement.id as movement_id', 'movement.source_type', 'movement.source_id']) as $row) {
            $reasons = [];
            $recordId = self::intProperty($row, 'id');
            $companyId = self::intProperty($row, 'company_id');
            $movementId = self::nullableIntProperty($row, 'movement_id');
            if ($movementId === null) {
                $reasons[] = 'INVENTORY_MOVEMENT_MISSING';
            } elseif (self::nullableStringProperty($row, 'source_type') !== 'sales_fulfillment') {
                $reasons[] = 'MOVEMENT_SOURCE_TYPE_MISMATCH';
            } elseif (self::nullableIntProperty($row, 'source_id') !== $recordId) {
                $reasons[] = 'MOVEMENT_SOURCE_ID_MISMATCH';
            }
            $journalIds = DB::table('payroll_accounting_journals')->where('company_id', $companyId)->where('source_type', 'sales_fulfillment')->where('source_id', $recordId)->whereIn('status', ['internally_posted', 'exported'])->pluck('id');
            $hasPolicy = $journalIds->isNotEmpty() && DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journalIds)->whereRaw("JSON_EXTRACT(mapping_snapshot, '$.policy_id') IS NOT NULL")->exists();
            if (! $hasPolicy) {
                $reasons[] = $journalIds->isEmpty() ? 'JOURNAL_MISSING' : 'JOURNAL_POLICY_EVIDENCE_MISSING';
            }

            if ($reasons !== []) {
                $result[] = ['record_key' => 'sales_fulfillment:'.$recordId, 'entity_type' => 'sales_fulfillment', 'record_id' => $recordId, 'company_id' => $companyId, 'branch_id' => null, 'movement_id' => $movementId, 'journal_ids' => self::integerList($journalIds->all()), 'reasons' => $reasons];
            }
        }

        return $result;
    }

    /** @return list<array<string,mixed>> */
    private function periodsWithoutFiscalYear(?int $companyId): array
    {
        if (! Schema::hasColumn('accounting_periods', 'fiscal_year_id')) {
            return [];
        }
        $query = DB::table('accounting_periods as period')->leftJoin('accounting_fiscal_years as fiscal', 'fiscal.id', '=', 'period.fiscal_year_id')->where(static fn (Builder $builder): Builder => $builder->whereNull('fiscal.id')->orWhereColumn('fiscal.company_id', '<>', 'period.company_id'));
        $this->scopeCompany($query, 'period.company_id', $companyId);

        return array_values($query->orderBy('period.id')->get(['period.id', 'period.company_id', 'period.fiscal_year_id', 'period.start_date', 'period.end_date', 'fiscal.company_id as fiscal_company_id'])->map(static function (object $row): array {
            $id = self::intProperty($row, 'id');
            $fiscalYearId = self::nullableIntProperty($row, 'fiscal_year_id');

            return ['record_key' => 'accounting_period:'.$id, 'entity_type' => 'accounting_period', 'record_id' => $id, 'company_id' => self::intProperty($row, 'company_id'), 'fiscal_year_id' => $fiscalYearId, 'start_date' => self::stringProperty($row, 'start_date'), 'end_date' => self::stringProperty($row, 'end_date'), 'reason' => $fiscalYearId === null ? 'FISCAL_YEAR_UNASSIGNED' : (self::nullableIntProperty($row, 'fiscal_company_id') === null ? 'FISCAL_YEAR_MISSING' : 'FISCAL_YEAR_CROSS_COMPANY')];
        })->all());
    }

    /**
     * @param  array<string,list<string>>  $sets
     * @return array<string,mixed>
     */
    private function relationship(string $left, string $right, array $sets): array
    {
        return ['left_rule' => $left, 'right_rule' => $right, 'left_count' => count($sets[$left]), 'right_count' => count($sets[$right]), 'same_row_set' => $sets[$left] === $sets[$right], 'left_only' => array_values(array_diff($sets[$left], $sets[$right])), 'right_only' => array_values(array_diff($sets[$right], $sets[$left]))];
    }

    private function scopeCompany(Builder $query, string $column, ?int $companyId): void
    {
        if ($companyId !== null) {
            $query->where($column, $companyId);
        }
    }

    private static function recordKey(mixed $record): string
    {
        if (! is_array($record) || ! array_key_exists('record_key', $record)) {
            throw new RuntimeException('Audit record is missing its deterministic key.');
        }

        return self::stringValue($record['record_key']);
    }

    private static function intProperty(object $row, string $property): int
    {
        return self::intValue($row->{$property} ?? null);
    }

    private static function nullableIntProperty(object $row, string $property): ?int
    {
        $value = $row->{$property} ?? null;

        return $value === null ? null : self::intValue($value);
    }

    private static function stringProperty(object $row, string $property): string
    {
        return self::stringValue($row->{$property} ?? null);
    }

    private static function nullableStringProperty(object $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return $value === null ? null : self::stringValue($value);
    }

    private static function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return intval($value);
        }

        throw new RuntimeException('Expected a non-negative integer database value.');
    }

    private static function stringValue(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Expected a string database value.');
        }

        return $value;
    }

    private static function boolValue(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new RuntimeException('Expected a boolean database-scope value.');
        }

        return $value;
    }

    /**
     * @param  iterable<mixed>  $values
     * @return list<int>
     */
    private static function integerList(iterable $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[] = self::intValue($value);
        }

        return $result;
    }
}
