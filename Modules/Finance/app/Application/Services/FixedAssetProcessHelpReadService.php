<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use App\Support\Erp\ProcessHelpReadService;

final readonly class FixedAssetProcessHelpReadService extends ProcessHelpReadService
{
    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        $s = ['page' => 'assets', 'company_id' => $companyId, 'branch_id' => $branchId];
        $assets = $this->url($actor, 'finance.operations.view', 'admin.finance.operations', $s);
        $proc = $this->url($actor, 'procurement.view', 'admin.procurement.reports', ['company_id' => $companyId, 'branch_id' => $branchId, 'classification' => 'asset']);
        $journal = $this->url($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.index', ['company_id' => $companyId, 'branch_id' => $branchId]);

        return ['title' => 'Fixed asset lifecycle workflow', 'description' => 'Lineage nguyên giá, activation, khấu hao, impairment, điều chuyển và thanh lý từ chứng từ canonical.', 'owner' => 'Finance Fixed Asset boundary', 'accounting_impact' => 'Capitalization ghi asset; depreciation phân bổ expense; impairment giảm book value; disposal ghi proceeds và gain/loss.', 'steps' => [
            $this->step('Asset Purchase', 'Procurement Module', 'GetProcurementPageQuery → ProcurementReadService', 'Purchase Order/Vendor Bill có classification asset.', 'Tạo AP và asset acquisition evidence.', 'Capitalization', $proc, $this->count('purchase_orders', $companyId, $branchId), 'Create from Purchase Request'),
            $this->step('Capitalization', 'Finance Module', 'GetFinanceOperationsQuery → FinanceOperationsService', 'Asset draft được kiểm tra và đưa vào sử dụng.', 'Dr Fixed Asset · Cr AP/Asset Clearing.', 'Asset Register', $assets, $this->count('fixed_assets', $companyId, $branchId), 'Create from Asset Purchase'),
            $this->step('Asset Register', 'Finance Module', 'GetFinanceOperationsQuery → FinanceOperationsService', 'Sổ tài sản canonical theo branch/cost center.', 'Giữ nguyên giá, khấu hao lũy kế và book value.', 'Depreciation', $assets, $this->count('fixed_assets', $companyId, $branchId), 'Capitalize upstream asset'),
            $this->step('Depreciation', 'Finance Module', 'GetFinanceOperationsQuery → FinanceOperationsService', 'Khấu hao theo kỳ và useful-life đã khóa.', 'Dr Depreciation Expense · Cr Accumulated Depreciation.', 'Impairment Review', $assets, $this->count('finance_depreciation_entries', $companyId, $branchId), 'Activate Asset upstream'),
            $this->step('Impairment Review', 'Finance Module', 'GetAssetLifecycleQuery → AssetLifecycleReadService', 'Đánh giá tổn thất theo controlled reason và maker-checker.', 'Dr Impairment Loss · Cr Asset Value.', 'Asset Transfer', $assets, $this->count('finance_asset_impairments', $companyId, $branchId), 'Create from active Asset'),
            $this->step('Asset Transfer', 'Finance Module', 'GetFinanceOperationsQuery → FinanceOperationsService', 'Điều chuyển owner branch/cost center có ngày hiệu lực.', 'Không đổi book value; thay dimension cho kỳ sau.', 'Disposal Request', $assets, $this->count('finance_asset_assignments', $companyId, $branchId), 'Create from active Asset'),
            $this->step('Disposal Request', 'Finance Module', 'GetAssetDisposalQuery → AssetLifecycleReadService', 'Đề nghị thanh lý khóa book value và proceeds tại thời điểm tạo.', 'Chưa ghi sổ cho tới khi approval và post.', 'Asset Retirement', $assets, $this->count('finance_asset_disposals', $companyId, $branchId), 'Create from active Asset'),
            $this->step('Asset Retirement', 'Finance Module', 'PostAssetDisposalCommand → AssetLifecycleService', 'Maker-checker hoàn tất retirement và đóng assignment hiện hành.', 'Loại asset còn lại; ghi cash proceeds và gain/loss.', 'Accounting Journal', $assets, $this->count('finance_asset_disposals', $companyId, $branchId), 'Approve Disposal Request upstream'),
            $this->step('Accounting Journal', 'Finance Module', 'AccountingJournalQuery → SourceAccountingPostingService', 'Journal cân bằng cho capitalization/depreciation/disposal.', 'Cập nhật GL và financial statements.', 'Financial reporting', $journal, $this->count('payroll_accounting_journals', $companyId, $branchId), 'Post asset workflow upstream'),
        ]];
    }
}
