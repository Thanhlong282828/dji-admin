<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use App\Support\Erp\ProcessHelpReadService;

final readonly class FinancialStatementProcessHelpReadService extends ProcessHelpReadService
{
    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        $scope = ['company_id' => $companyId, 'branch_id' => $branchId];
        $journals = $this->url($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.index', $scope);
        $statements = $this->url($actor, 'payroll.accounting.view', 'admin.finance.financial-statements', $scope);

        return [
            'title' => 'Financial statement lineage',
            'description' => 'Luồng read-only từ transaction đã post tới ledger và báo cáo; correction chỉ đi qua reversal journal.',
            'owner' => 'Finance / Accounting reporting boundary',
            'accounting_impact' => 'Không tạo posting mới. Báo cáo chỉ tổng hợp journal immutable ở trạng thái internally_posted hoặc exported.',
            'steps' => [
                $this->step('Transaction', 'Source business modules', 'Source commands → Accounting Posting Service', 'Chứng từ nghiệp vụ canonical tạo accounting evidence.', 'Chưa vào báo cáo cho tới khi journal được post.', 'Accounting Journal', $journals, 0, 'Post an upstream transaction'),
                $this->step('Accounting Journal', 'Finance Module', 'AccountingJournalQuery', 'Bút toán maker-checker cân Nợ/Có và khóa policy snapshot.', 'Ghi nhận GL bất biến; sửa sai bằng reversal.', 'General Ledger', $journals, $this->count('payroll_accounting_journals', $companyId, $branchId), 'Post upstream transaction'),
                $this->step('General Ledger', 'Finance Module', 'GetFinancialStatementsQuery → FinancialStatementReadService', 'Journal lines theo account, branch, currency và kỳ báo cáo.', 'Cung cấp số phát sinh và số dư tài khoản.', 'Financial Statements', $statements, $this->count('payroll_accounting_journal_lines', $companyId, $branchId), 'Post Accounting Journal upstream'),
                $this->step('Financial Statements', 'Finance Module', 'GetFinancialStatementsQuery → FinancialStatementReadService', 'Trial Balance, Balance Sheet, Income, Cash Flow và Equity cùng truy về ledger.', 'Read-only; không bypass posting hoặc sửa journal.', 'Period close / reporting', $statements, 0, 'Post Accounting Journal upstream'),
            ],
        ];
    }
}
