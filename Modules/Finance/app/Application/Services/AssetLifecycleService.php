<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class AssetLifecycleService
{
    private const IMPAIRMENT_REASONS = ['LOSS_OF_VALUE', 'DAMAGE', 'MARKET_CHANGE', 'OBSOLESCENCE'];

    public function __construct(private ConnectionInterface $db, private AccountingPostingPolicyResolver $policies, private SourceAccountingPostingService $accounting) {}

    /** @param array<string,mixed> $data */
    public function createImpairment(int $companyId, User $actor, array $data): int
    {
        $this->guard($companyId, $actor);
        $reason = strtoupper($this->text($data, 'reason'));
        if (! in_array($reason, self::IMPAIRMENT_REASONS, true)) {
            throw ValidationException::withMessages(['reason' => 'ASSET_IMPAIRMENT_REASON_INVALID']);
        }

        return $this->db->transaction(function () use ($companyId, $actor, $data, $reason): int {
            $this->lockCompany($companyId);
            $key = $this->text($data, 'idempotency_key');
            $existing = $this->db->table('finance_asset_impairments')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $asset = $this->asset($companyId, $this->positive($data, 'asset_id'), true);
            $book = $this->bookValue($asset);
            $amount = $this->positive($data, 'impairment_amount_minor');
            if ($amount > max(0, $book - $this->integer($asset, 'residual_value_minor'))) {
                throw ValidationException::withMessages(['impairment_amount_minor' => 'ASSET_IMPAIRMENT_EXCEEDS_RECOVERABLE_BOOK_VALUE']);
            }

            return (int) $this->db->table('finance_asset_impairments')->insertGetId([
                'company_id' => $companyId, 'asset_id' => $asset->id, 'reference' => $this->text($data, 'reference'), 'reason' => $reason,
                'review_date' => $this->text($data, 'review_date'), 'previous_book_value_minor' => $book, 'impairment_amount_minor' => $amount,
                'currency' => $asset->currency, 'status' => 'draft', 'idempotency_key' => $key, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function approveImpairment(int $companyId, int $id, User $actor): int
    {
        return $this->approve($companyId, $id, $actor, 'finance_asset_impairments', 'ASSET_IMPAIRMENT');
    }

    public function postImpairment(int $companyId, int $id, User $actor): int
    {
        $this->guard($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $document = $this->impairmentDocument($companyId, $id);
            if ($document->status === 'posted') {
                return $id;
            }
            if ($document->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'ASSET_IMPAIRMENT_NOT_APPROVED']);
            }
            $asset = $this->asset($companyId, (int) $document->asset_id, true);
            $policy = $this->policies->resolve($companyId, 'ASSET_IMPAIRMENT', $document->reason, $document->review_date, $document->currency);
            $amount = (int) $document->impairment_amount_minor;
            $lines = $this->lines($policy['lines'], [['IMPAIRMENT_LOSS', 'debit', $amount], ['ASSET_VALUE', 'credit', $amount]], 'Asset impairment');
            $journal = $this->accounting->postAssetLifecycle($companyId, (int) $asset->branch_id, 'asset_impairment', $id, $document->review_date, $document->currency, $lines, (int) $actor->id, $policy['policy_id'], $policy['version']);
            $this->posted('finance_asset_impairments', $id, $actor, $journal, $policy);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function createDisposal(int $companyId, User $actor, array $data): int
    {
        $this->guard($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $this->lockCompany($companyId);
            $key = $this->text($data, 'idempotency_key');
            $existing = $this->db->table('finance_asset_disposals')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $asset = $this->asset($companyId, $this->positive($data, 'asset_id'), true);
            $book = $this->bookValue($asset);
            $proceeds = $this->nonNegative($data, 'proceeds_minor');

            return (int) $this->db->table('finance_asset_disposals')->insertGetId([
                'company_id' => $companyId, 'asset_id' => $asset->id, 'reference' => $this->text($data, 'reference'),
                'disposal_date' => $this->text($data, 'disposal_date'), 'reason' => $this->text($data, 'reason'), 'proceeds_minor' => $proceeds,
                'book_value_minor' => $book, 'gain_loss_minor' => $proceeds - $book, 'currency' => $asset->currency, 'status' => 'draft',
                'idempotency_key' => $key, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function approveDisposal(int $companyId, int $id, User $actor): int
    {
        return $this->approve($companyId, $id, $actor, 'finance_asset_disposals', 'ASSET_DISPOSAL');
    }

    public function postDisposal(int $companyId, int $id, User $actor): int
    {
        $this->guard($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $document = $this->disposalDocument($companyId, $id);
            if ($document->status === 'posted') {
                return $id;
            }
            if ($document->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'ASSET_DISPOSAL_NOT_APPROVED']);
            }
            $asset = $this->asset($companyId, (int) $document->asset_id, true);
            $currentBook = $this->bookValue($asset);
            if ($currentBook !== (int) $document->book_value_minor) {
                throw ValidationException::withMessages(['book_value_minor' => 'ASSET_DISPOSAL_BOOK_VALUE_CHANGED']);
            }
            $gainLoss = (int) $document->gain_loss_minor;
            $class = $gainLoss > 0 ? 'GAIN' : ($gainLoss < 0 ? 'LOSS' : 'EVEN');
            $policy = $this->policies->resolve($companyId, 'ASSET_DISPOSAL', $class, $document->disposal_date, $document->currency);
            $depreciation = (int) $this->db->table('finance_depreciation_entries')->where(['company_id' => $companyId, 'asset_id' => $asset->id])->sum('amount_minor');
            $impairment = (int) $this->db->table('finance_asset_impairments')->where(['company_id' => $companyId, 'asset_id' => $asset->id, 'status' => 'posted'])->sum('impairment_amount_minor');
            $remainingAssetCost = max(0, (int) $asset->acquisition_cost_minor - $impairment);
            $spec = [['ACCUMULATED_DEPRECIATION', 'debit', $depreciation], ['CASH_PROCEEDS', 'debit', (int) $document->proceeds_minor], ['DISPOSAL_LOSS', 'debit', max(0, -$gainLoss)], ['FIXED_ASSET', 'credit', $remainingAssetCost], ['DISPOSAL_GAIN', 'credit', max(0, $gainLoss)]];
            $lines = $this->lines($policy['lines'], $spec, 'Asset disposal');
            $journal = $this->accounting->postAssetLifecycle($companyId, (int) $asset->branch_id, 'asset_disposal', $id, $document->disposal_date, $document->currency, $lines, (int) $actor->id, $policy['policy_id'], $policy['version']);
            $this->posted('finance_asset_disposals', $id, $actor, $journal, $policy);
            $this->db->table('finance_assets')->where('id', $asset->id)->update(['status' => 'disposed', 'disposed_on' => $document->disposal_date, 'disposal_journal_id' => $journal, 'updated_at' => now()]);
            $this->db->table('finance_asset_assignments')->where('asset_id', $asset->id)->whereNull('effective_to')->update(['effective_to' => $document->disposal_date, 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    private function approve(int $companyId, int $id, User $actor, string $table, string $error): int
    {
        $this->guard($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $id, $actor, $table, $error): int {
            $document = $this->document($table, $companyId, $id);
            if ($document->status === 'approved') {
                throw ValidationException::withMessages(['approval' => $error.'_ALREADY_APPROVED']);
            }
            if ($document->status !== 'draft' || (int) $document->created_by === (int) $actor->id) {
                throw ValidationException::withMessages(['approval' => $error.'_MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table($table)->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /**
     * @param  list<array{entry_side:string,line_role:string,account_code:string}>  $policyLines
     * @param  list<array{0:string,1:string,2:int}>  $spec
     * @return list<array{account_code:string,debit:int,credit:int,description:string}>
     */
    private function lines(array $policyLines, array $spec, string $description): array
    {
        $result = [];
        foreach ($spec as [$role, $side, $amount]) {
            if ($amount === 0) {
                continue;
            }
            $match = collect($policyLines)->first(fn (array $line): bool => $line['line_role'] === $role && $line['entry_side'] === $side);
            if (! is_array($match)) {
                throw ValidationException::withMessages(['accounting_policy' => 'ASSET_LIFECYCLE_POLICY_ROLE_MISSING:'.$role]);
            }
            $result[] = ['account_code' => $match['account_code'], 'debit' => $side === 'debit' ? $amount : 0, 'credit' => $side === 'credit' ? $amount : 0, 'description' => $description];
        }

        return $result;
    }

    /** @param array<string,mixed> $policy */
    private function posted(string $table, int $id, User $actor, int $journal, array $policy): void
    {
        $this->db->table($table)->where('id', $id)->update(['status' => 'posted', 'accounting_policy_snapshot' => json_encode(['id' => $policy['policy_id'], 'version' => $policy['version'], 'key' => $policy['policy_key']], JSON_THROW_ON_ERROR), 'accounting_journal_id' => $journal, 'posted_by' => $actor->id, 'posted_at' => now(), 'updated_at' => now()]);
    }

    private function bookValue(AssetLifecycleAssetRow $asset): int
    {
        $depreciation = (int) $this->db->table('finance_depreciation_entries')->where(['company_id' => $asset->company_id, 'asset_id' => $asset->id])->sum('amount_minor');
        $impairment = Schema::hasTable('finance_asset_impairments') ? (int) $this->db->table('finance_asset_impairments')->where(['company_id' => $asset->company_id, 'asset_id' => $asset->id, 'status' => 'posted'])->sum('impairment_amount_minor') : 0;

        return max(0, (int) $asset->acquisition_cost_minor - $depreciation - $impairment);
    }

    private function asset(int $companyId, int $id, bool $lock): AssetLifecycleAssetRow
    {
        $query = $this->db->table('finance_assets')->where(['id' => $id, 'company_id' => $companyId, 'status' => 'active']);

        return new AssetLifecycleAssetRow(($lock ? $query->lockForUpdate() : $query)->firstOrFail());
    }

    private function document(string $table, int $companyId, int $id): AssetLifecycleApprovalRow
    {
        return new AssetLifecycleApprovalRow($this->db->table($table)->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail());
    }

    private function impairmentDocument(int $companyId, int $id): AssetLifecycleImpairmentRow
    {
        return new AssetLifecycleImpairmentRow($this->db->table('finance_asset_impairments')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail());
    }

    private function disposalDocument(int $companyId, int $id): AssetLifecycleDisposalRow
    {
        return new AssetLifecycleDisposalRow($this->db->table('finance_asset_disposals')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail());
    }

    private function guard(int $companyId, User $actor): void
    {
        if (! Schema::hasTable('finance_asset_impairments') || ! Schema::hasTable('finance_asset_disposals')) {
            throw ValidationException::withMessages(['schema' => 'SCHEMA_UPGRADE_REQUIRED']);
        }
        abort_unless($actor->hasRole('Super Admin') || $this->db->table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $actor->id])->exists(), 404);
    }

    private function lockCompany(int $companyId): void
    {
        $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->firstOrFail(['id']);
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw ValidationException::withMessages([$key => 'REQUIRED']);
    }

    /** @param array<string,mixed> $data */
    private function positive(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : throw ValidationException::withMessages([$key => 'POSITIVE_REQUIRED']);
    }

    /** @param array<string,mixed> $data */
    private function nonNegative(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) && (int) $value >= 0 ? (int) $value : throw ValidationException::withMessages([$key => 'NON_NEGATIVE_REQUIRED']);
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid asset integer.');
    }
}

abstract readonly class AssetLifecycleRow
{
    final protected static function int(stdClass $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid asset lifecycle integer: '.$key);
    }

    final protected static function string(stdClass $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid asset lifecycle string: '.$key);
    }
}

final readonly class AssetLifecycleAssetRow extends AssetLifecycleRow
{
    public int $id;

    public int $company_id;

    public int $branch_id;

    public int $acquisition_cost_minor;

    public int $residual_value_minor;

    public string $currency;

    public function __construct(stdClass $row)
    {
        $this->id = self::int($row, 'id');
        $this->company_id = self::int($row, 'company_id');
        $this->branch_id = self::int($row, 'branch_id');
        $this->acquisition_cost_minor = self::int($row, 'acquisition_cost_minor');
        $this->residual_value_minor = self::int($row, 'residual_value_minor');
        $this->currency = self::string($row, 'currency');
    }
}

final readonly class AssetLifecycleApprovalRow extends AssetLifecycleRow
{
    public string $status;

    public int $created_by;

    public function __construct(stdClass $row)
    {
        $this->status = self::string($row, 'status');
        $this->created_by = self::int($row, 'created_by');
    }
}

final readonly class AssetLifecycleImpairmentRow extends AssetLifecycleRow
{
    public string $status;

    public int $asset_id;

    public string $reason;

    public string $review_date;

    public string $currency;

    public int $impairment_amount_minor;

    public function __construct(stdClass $row)
    {
        $this->status = self::string($row, 'status');
        $this->asset_id = self::int($row, 'asset_id');
        $this->reason = self::string($row, 'reason');
        $this->review_date = self::string($row, 'review_date');
        $this->currency = self::string($row, 'currency');
        $this->impairment_amount_minor = self::int($row, 'impairment_amount_minor');
    }
}

final readonly class AssetLifecycleDisposalRow extends AssetLifecycleRow
{
    public string $status;

    public int $asset_id;

    public string $disposal_date;

    public string $currency;

    public int $book_value_minor;

    public int $gain_loss_minor;

    public int $proceeds_minor;

    public function __construct(stdClass $row)
    {
        $this->status = self::string($row, 'status');
        $this->asset_id = self::int($row, 'asset_id');
        $this->disposal_date = self::string($row, 'disposal_date');
        $this->currency = self::string($row, 'currency');
        $this->book_value_minor = self::int($row, 'book_value_minor');
        $this->gain_loss_minor = self::int($row, 'gain_loss_minor');
        $this->proceeds_minor = self::int($row, 'proceeds_minor');
    }
}
