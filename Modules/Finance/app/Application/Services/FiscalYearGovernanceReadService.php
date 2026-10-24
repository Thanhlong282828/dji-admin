<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use App\Support\Erp\ProcessHelpReadService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final readonly class FiscalYearGovernanceReadService extends ProcessHelpReadService
{
    /** @return array<string,mixed> */
    public function workspace(int $companyId, User $actor): array
    {
        if (! Schema::hasTable('accounting_fiscal_years')) {
            return ['schema_ready' => false, 'years' => collect(), 'periods' => collect(), 'closes' => collect(), 'opening_balances' => collect(), 'metrics' => ['open_years' => 0, 'closed_periods' => 0, 'pending_closes' => 0, 'opening_lines' => 0], 'accounts' => collect(), 'process_help' => $this->help($companyId, $actor)];
        }
        $years = $this->db->table('accounting_fiscal_years')->where('company_id', $companyId)->orderByDesc('start_date')->get();
        $periods = $this->db->table('accounting_periods as period')->leftJoin('accounting_fiscal_years as fiscal', 'fiscal.id', '=', 'period.fiscal_year_id')->where('period.company_id', $companyId)->orderByDesc('period.start_date')->get(['period.*', 'fiscal.name as fiscal_year_name']);
        $closes = $this->db->table('accounting_fiscal_year_close_requests as close')->join('accounting_fiscal_years as fiscal', 'fiscal.id', '=', 'close.fiscal_year_id')->leftJoin('payroll_accounting_journals as closing', 'closing.id', '=', 'close.closing_journal_id')->leftJoin('payroll_accounting_journals as opening', 'opening.id', '=', 'close.opening_journal_id')->where('close.company_id', $companyId)->orderByDesc('close.id')->get(['close.*', 'fiscal.name as fiscal_year_name', 'closing.journal_number as closing_journal_number', 'opening.journal_number as opening_journal_number']);
        $opening = $this->db->table('accounting_opening_balance_lines as line')->join('accounting_accounts as account', 'account.id', '=', 'line.account_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'line.opening_journal_id')->where('line.company_id', $companyId)->orderBy('account.code')->get(['line.*', 'account.code as account_code', 'account.name as account_name', 'journal.journal_number']);

        return ['schema_ready' => true, 'years' => $years, 'periods' => $periods, 'closes' => $closes, 'opening_balances' => $opening, 'metrics' => ['open_years' => $years->where('status', 'open')->count(), 'closed_periods' => $periods->where('status', 'closed')->count(), 'pending_closes' => $closes->whereIn('status', ['draft', 'submitted'])->count(), 'opening_lines' => $opening->count()], 'accounts' => $this->db->table('accounting_accounts')->where(['company_id' => $companyId, 'account_type' => 'equity', 'status' => 'active'])->orderBy('code')->get(['id', 'code', 'name']), 'process_help' => $this->help($companyId, $actor)];
    }

    /** @return array<string,mixed> */
    public function openingBalances(int $companyId, User $actor): array
    {
        $workspace = $this->workspace($companyId, $actor);

        return ['schema_ready' => $workspace['schema_ready'], 'opening_balances' => $workspace['opening_balances'], 'process_help' => $workspace['process_help']];
    }

    /** @return array<string,mixed> */
    private function help(int $companyId, User $actor): array
    {
        $scope = ['company_id' => $companyId];
        $periods = $actor->can('accounting.period.view') && Route::has('admin.finance.fiscal-years') ? route('admin.finance.fiscal-years', $scope) : null;
        $journals = $actor->can('payroll.accounting.view') ? route('admin.payroll.accounting.journals.index', $scope) : null;
        $statements = $actor->can('payroll.accounting.view') ? route('admin.finance.financial-statements', $scope) : null;

        return ['title' => 'Fiscal year & period governance', 'description' => 'Transaction chỉ vào kỳ mở; year close chuyển P&L sang retained earnings và tạo opening balance có lineage.', 'owner' => 'Finance / General Accounting', 'accounting_impact' => 'Close journal khóa Revenue/Expense; carry-forward chỉ mang Asset/Liability/Equity sang fiscal year kế tiếp.', 'steps' => [
            $this->step('Transaction', 'Business Modules', 'Posting Commands → SourceAccountingPostingService', 'Chứng từ canonical yêu cầu posting date thuộc kỳ mở.', 'Tạo balanced immutable Journal.', 'Journal', $journals, 0, 'Create upstream transaction'),
            $this->step('Journal', 'Finance Module', 'AccountingJournalQuery', 'Journal đã post và khóa policy/source evidence.', 'Cập nhật General Ledger.', 'Accounting Period', $journals, 0, 'Post upstream transaction'),
            $this->step('Accounting Period', 'Finance Module', 'GetPeriodCloseQuery → FiscalYearGovernanceReadService', 'Khoảng ngày kế toán thuộc một Fiscal Year.', 'Kỳ đóng từ chối posting thường.', 'Period Close', $periods, 0, 'Create Fiscal Year and Period'),
            $this->step('Period Close', 'Finance Module', 'CloseAccountingPeriodCommand → AccountingPeriodCloseService', 'Maker-checker hoàn tất checklist trước close.', 'Khóa posting date trong kỳ.', 'Fiscal Year Close', $periods, 0, 'Resolve close blockers'),
            $this->step('Fiscal Year', 'Finance Module', 'CloseFiscalYearCommand → FiscalYearGovernanceService', 'Đóng toàn bộ P&L vào retained earnings.', 'Balanced closing journal.', 'Opening Balance', $statements, 0, 'Close all periods'),
            $this->step('Opening Balance', 'Finance Module', 'CarryForwardBalanceCommand → FiscalYearGovernanceService', 'Mang số dư Asset/Liability/Equity có source lineage sang năm kế tiếp.', 'Balanced opening journal.', 'Next Fiscal Year', $periods, 0, 'Open next Fiscal Year'),
        ]];
    }
}
